#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <sys/socket.h>
#include <sys/un.h> // Для Unix-сокетов
#include <sys/stat.h> // Для chmod
#include <sys/wait.h> // Для waitpid
#include <json-c/json.h> // Для работы с JSON
#include <pwd.h> // Для getpwnam и структуры passwd

#define SOCKET_PATH "/tmp/command_executor.sock" // Путь к файлу Unix-сокета
#define BUFFER_SIZE 4096

// Функция для выполнения команды в отдельном, отсоединенном процессе (как раньше)
void execute_command_detached(const char *command_str, const char *user, json_object *env_json) {
    pid_t pid = fork(); // Первый fork

    if (pid < 0) {
        perror("fork failed (first)");
        return;
    }

    if (pid > 0) {
        // Родительский процесс (ретранслятор)
        return; // Не ждем завершения дочернего процесса
    }

    // Дочерний процесс (первый fork)
    pid_t grand_child_pid = fork();

    if (grand_child_pid < 0) {
        perror("fork failed (second)");
        _exit(EXIT_FAILURE);
    }

    if (grand_child_pid > 0) {
        // Первый дочерний процесс завершается, делая "внука" сиротой
        _exit(EXIT_SUCCESS);
    }

    // Процесс "внука" (этот процесс будет выполнять команду)
    if (setsid() < 0) {
        perror("setsid failed");
        _exit(EXIT_FAILURE);
    }

    if (chdir("/") < 0) {
        perror("chdir failed");
    }

    close(STDIN_FILENO);
    close(STDOUT_FILENO);
    close(STDERR_FILENO);

    char *args[BUFFER_SIZE / 2];
    int arg_idx = 0;

    args[arg_idx++] = strdup("/usr/bin/sudo"); // Путь к sudo
    args[arg_idx++] = strdup("-u");
    args[arg_idx++] = strdup(user);

    char *cmd_copy = strdup(command_str);
    char *token = strtok(cmd_copy, " ");
    while (token != NULL && arg_idx < (BUFFER_SIZE / 2) - 1) {
        args[arg_idx++] = strdup(token);
        token = strtok(NULL, " ");
    }
    args[arg_idx] = NULL;

    char *envp[100];
    int env_idx = 0;
    extern char **environ;
    for (char **current_env = environ; *current_env != NULL; current_env++) {
        if (env_idx < 99) {
            envp[env_idx++] = *current_env;
        }
    }

    if (env_json) {
        json_object_object_foreach(env_json, key, val) {
            const char *env_key = key;
            const char *env_val = json_object_get_string(val);
            char env_str[BUFFER_SIZE];
            snprintf(env_str, BUFFER_SIZE, "%s=%s", env_key, env_val);
            if (env_idx < 99) {
                envp[env_idx++] = strdup(env_str);
            }
        }
    }
    envp[env_idx] = NULL;

    execve(args[0], args, envp);

    perror("execve failed");
    _exit(EXIT_FAILURE);
}

// НОВАЯ ФУНКЦИЯ: Выполнение команды синхронно и захват stdout/stderr
void execute_command_sync(const char *command_str, const char *user, char *stdout_buf, char *stderr_buf, int buf_size) {
    char full_command[BUFFER_SIZE];
    // Используем sudo -u и перенаправляем stderr в stdout для захвата popen
    snprintf(full_command, BUFFER_SIZE, "sudo -u %s %s 2>&1", user, command_str);

    FILE *fp = popen(full_command, "r");
    if (fp == NULL) {
        snprintf(stderr_buf, buf_size, "Failed to run command or open pipe.");
        stdout_buf[0] = '\0';
        return;
    }

    // Чтение всего вывода (stdout и stderr объединены)
    char line[256];
    stdout_buf[0] = '\0';
    stderr_buf[0] = '\0'; // В этом режиме stderr будет пустым, т.к. объединен с stdout

    while (fgets(line, sizeof(line), fp) != NULL) {
        strncat(stdout_buf, line, buf_size - strlen(stdout_buf) - 1);
    }

    int status = pclose(fp);
    if (status != 0) {
        snprintf(stderr_buf, buf_size, "Command exited with status %d", WEXITSTATUS(status));
    }
}


int main(int argc, char *argv[]) {
    int server_fd, client_fd;
    struct sockaddr_un server_addr, client_addr;
    socklen_t client_len;
    char buffer[BUFFER_SIZE];
    char stdout_result[BUFFER_SIZE];
    char stderr_result[BUFFER_SIZE];
    const char *target_user;

    if (argc < 2) {
        fprintf(stderr, "Использование: %s <имя_пользователя>\n", argv[0]);
        exit(EXIT_FAILURE);
    }

    target_user = argv[1];
    printf("Команды будут выполняться от имени пользователя: %s\n", target_user);

    server_fd = socket(AF_UNIX, SOCK_STREAM, 0);
    if (server_fd == -1) {
        perror("socket error");
        exit(EXIT_FAILURE);
    }

    unlink(SOCKET_PATH); // Удаляем старый файл сокета

    memset(&server_addr, 0, sizeof(server_addr));
    server_addr.sun_family = AF_UNIX;
    strncpy(server_addr.sun_path, SOCKET_PATH, sizeof(server_addr.sun_path) - 1);

    if (bind(server_fd, (struct sockaddr *)&server_addr, sizeof(server_addr)) == -1) {
        perror("bind error");
        close(server_fd);
        exit(EXIT_FAILURE);
    }

    if (listen(server_fd, 5) == -1) {
        perror("listen error");
        close(server_fd);
        exit(EXIT_FAILURE);
    }

    printf("Слушаю на Unix-сокете: %s\n", SOCKET_PATH);

    // Установка прав доступа к файлу сокета
    struct passwd *pw = getpwnam("www-data");
    if (pw != NULL) {
        if (chown(SOCKET_PATH, -1, pw->pw_gid) == -1) {
            perror("chown error on socket file (group)");
        }
    } else {
        fprintf(stderr, "Warning: User 'www-data' not found, cannot set socket group ownership.\n");
    }
    chmod(SOCKET_PATH, 0660); // Чтение/запись для владельца (root) и группы (www-data)

    while (1) {
        client_len = sizeof(client_addr);
        client_fd = accept(server_fd, (struct sockaddr *)&client_addr, &client_len);
        if (client_fd == -1) {
            perror("accept error");
            continue;
        }

        printf("Подключился клиент.\n");

        ssize_t bytes_received = recv(client_fd, buffer, BUFFER_SIZE - 1, 0);
        if (bytes_received > 0) {
            buffer[bytes_received] = '\0';
            printf("Получено: %s\n", buffer);

            json_object *request_json = json_tokener_parse(buffer);
            json_object *command_obj;
            json_object *env_obj = NULL;
            json_object *sync_obj = NULL; // Флаг для синхронного выполнения

            json_object *response_json = json_object_new_object();
            int is_sync_request = 0;

            if (request_json) {
                json_object_object_get_ex(request_json, "sync", &sync_obj);
                if (sync_obj && json_object_get_boolean(sync_obj)) {
                    is_sync_request = 1;
                }
            }

            if (request_json && json_object_object_get_ex(request_json, "command", &command_obj)) {
                const char *command_str = json_object_get_string(command_obj);

                if (is_sync_request) {
                    printf("Выполняю команду синхронно: %s от имени %s\n", command_str, target_user);
                    execute_command_sync(command_str, target_user, stdout_result, stderr_result, BUFFER_SIZE);
                    json_object_object_add(response_json, "status", json_object_new_string("success"));
                    json_object_object_add(response_json, "stdout", json_object_new_string(stdout_result));
                    json_object_object_add(response_json, "stderr", json_object_new_string(stderr_result));
                } else {
                    json_object_object_get_ex(request_json, "env", &env_obj);
                    printf("Запускаю команду отдельно: %s от имени %s\n", command_str, target_user);
                    execute_command_detached(command_str, target_user, env_obj);
                    json_object_object_add(response_json, "status", json_object_new_string("success"));
                    json_object_object_add(response_json, "message", json_object_new_string("Команда запущена в фоновом режиме. Вывод не захватывается."));
                }
            } else {
                json_object_object_add(response_json, "status", json_object_new_string("error"));
                json_object_object_add(response_json, "stderr", json_object_new_string("Команда не указана или неверный JSON."));
            }

            const char *response_str = json_object_to_json_string(response_json);
            send(client_fd, response_str, strlen(response_str), 0);

            if (request_json) json_object_put(request_json);
            json_object_put(response_json);
        } else if (bytes_received == 0) {
            printf("Клиент отключился.\n");
        } else {
            perror("recv error");
        }

        close(client_fd);
    }

    close(server_fd);
    unlink(SOCKET_PATH);
    return 0;
}
