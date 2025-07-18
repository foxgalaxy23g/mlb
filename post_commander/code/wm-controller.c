#include <X11/Xlib.h>
#include <X11/Xatom.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <json-c/json.h> // Новое включение для работы с JSON
#include <stdarg.h> // Для malloc_sprintf

// --- Константы API (должны совпадать с теми, что в WM) ---
#define API_CMD_LIST_WINDOWS      1
#define API_CMD_GET_WINDOW_NAME   2
#define API_CMD_SET_FOCUS         3
#define API_CMD_MINIMIZE          4
#define API_CMD_MAXIMIZE          5
#define API_CMD_CLOSE             6

// --- Ответы API ---
#define API_RESPONSE_WINDOW_INFO  101
#define API_RESPONSE_LIST_END     102

// --- Вспомогательная функция для sprintf в выделенную память ---
char *malloc_sprintf(const char *format, ...) {
    va_list args;
    va_start(args, format);
    int size = vsnprintf(NULL, 0, format, args);
    va_end(args);

    char *str = (char*)malloc(size + 1);
    if (!str) {
        perror("malloc_sprintf: malloc failed");
        return NULL;
    }

    va_start(args, format);
    vsnprintf(str, size + 1, format, args);
    va_end(args);
    return str;
}

// --- Функция для вывода справки по использованию ---
void print_usage(const char *prog_name) {
    fprintf(stderr, "Использование: %s <команда> [window_id]\n", prog_name);
    fprintf(stderr, "Команды:\n");
    fprintf(stderr, "  list        - Вывести список всех управляемых окон (вывод в JSON)\n");
    fprintf(stderr, "  focus <id>  - Установить фокус на окно по ID\n");
    fprintf(stderr, "  minimize <id> - Свернуть окно по ID\n");
    fprintf(stderr, "  maximize <id> - Развернуть окно по ID\n");
    fprintf(stderr, "  close <id>  - Закрыть окно по ID\n");
}

// --- Функция для отправки API команды WM ---
void send_api_command(Display *dpy, Window root, Window requestor, long cmd, long arg1) {
    XEvent ev;
    memset(&ev, 0, sizeof(ev));
    ev.xclient.type = ClientMessage;
    ev.xclient.display = dpy;
    ev.xclient.window = root; // Отправляем на корневое окно, WM слушает там
    ev.xclient.message_type = XInternAtom(dpy, "_TINYWM_PLUS_API", False);
    ev.xclient.format = 32;
    ev.xclient.data.l[0] = cmd;
    ev.xclient.data.l[1] = arg1;
    ev.xclient.data.l[2] = (long)requestor; // ID окна-запросчика
    XSendEvent(dpy, root, False, NoEventMask, &ev);
    XFlush(dpy); // Убедимся, что событие отправлено немедленно
}

// --- Функция для получения имени окна через PropertyNotify ---
char *get_window_name_from_property(Display *dpy, Window requestor, Atom api_response_atom) {
    char *win_name = NULL;
    XEvent name_ev;
    int name_received = 0;
    // Ожидаем PropertyNotify событие на окне requestor
    // XCheckTypedEvent или XIfEvent могут быть лучше для неблокирующего ожидания
    // Но для простоты и синхронного запроса пока используем XNextEvent
    while (!name_received && XNextEvent(dpy, &name_ev)) {
        if (name_ev.type == PropertyNotify && name_ev.xproperty.window == requestor && name_ev.xproperty.atom == api_response_atom) {
            Atom actual_type;
            int actual_format;
            unsigned long nitems, bytes_after;
            unsigned char *prop_data = NULL;
            if (XGetWindowProperty(dpy, requestor, api_response_atom, 0, 1024, False, XA_STRING,
                                   &actual_type, &actual_format, &nitems, &bytes_after, &prop_data) == Success && prop_data) {
                win_name = strdup((char*)prop_data);
                XFree(prop_data);
            }
            name_received = 1;
        }
    }
    return win_name;
}

// --- Функция для вывода списка окон в JSON формате ---
void list_windows(Display *dpy, Window root, Window requestor, Atom api_atom, Atom api_response_atom) {
    json_object *json_array = json_object_new_array();

    // Отправляем команду LIST_WINDOWS WM
    send_api_command(dpy, root, requestor, API_CMD_LIST_WINDOWS, 0);

    // Ожидаем ответы от WM
    XEvent ev;
    while (XNextEvent(dpy, &ev)) {
        if (ev.type == ClientMessage && ev.xclient.message_type == api_response_atom) {
            if (ev.xclient.data.l[0] == API_RESPONSE_WINDOW_INFO) {
                Window win_id = (Window)ev.xclient.data.l[1];
                long state = ev.xclient.data.l[2];
                char *win_name = NULL;

                // Запрашиваем имя окна у WM
                send_api_command(dpy, root, requestor, API_CMD_GET_WINDOW_NAME, (long)win_id);
                win_name = get_window_name_from_property(dpy, requestor, api_response_atom);

                json_object *window_obj = json_object_new_object();
                json_object_object_add(window_obj, "id", json_object_new_string(malloc_sprintf("0x%lx", win_id)));
                json_object_object_add(window_obj, "name", json_object_new_string(win_name ? win_name : "(Unnamed)"));
                json_object_object_add(window_obj, "state", json_object_new_int((int)state));
                json_object_array_add(json_array, window_obj);

                if (win_name) free(win_name); // Освобождаем дублированную строку
            } else if (ev.xclient.data.l[0] == API_RESPONSE_LIST_END) {
                break; // Получили сигнал об окончании списка
            }
        }
    }

    // Выводим JSON-массив в stdout
    printf("%s\n", json_object_to_json_string_ext(json_array, JSON_C_TO_STRING_PLAIN));
    json_object_put(json_array); // Освобождаем JSON объект
}


int main(int argc, char *argv[]) {
    Display *dpy;
    Window root;
    Window self; // Окно клиента для получения ответов
    Atom api_atom;
    Atom api_response_atom;

    if (argc < 2) {
        print_usage(argv[0]);
        return 1;
    }

    if (!(dpy = XOpenDisplay(NULL))) {
        fprintf(stderr, "Не удалось открыть дисплей\n");
        return 1;
    }

    root = DefaultRootWindow(dpy);
    // Создаем простое окно для этого клиента, чтобы получать события PropertyNotify
    self = XCreateSimpleWindow(dpy, root, 0, 0, 1, 1, 0, 0, 0);
    XSelectInput(dpy, self, PropertyChangeMask); // Выбираем PropertyChangeMask для получения ответов с именами

    api_atom = XInternAtom(dpy, "_TINYWM_PLUS_API", False);
    api_response_atom = XInternAtom(dpy, "_TINYWM_PLUS_API_RESPONSE", False);

    const char *command = argv[1];

    if (strcmp(command, "list") == 0) {
        list_windows(dpy, root, self, api_atom, api_response_atom);
    } else {
        if (argc < 3) {
            print_usage(argv[0]);
            XCloseDisplay(dpy);
            return 1;
        }
        // Парсим ID окна (может быть шестнадцатеричным или десятичным)
        Window win_id;
        if (strncmp(argv[2], "0x", 2) == 0 || strncmp(argv[2], "0X", 2) == 0) {
            win_id = strtol(argv[2], NULL, 16); // Шестнадцатеричное
        } else {
            win_id = strtol(argv[2], NULL, 10); // Десятичное
        }

        long api_cmd = 0;

        if (strcmp(command, "focus") == 0) api_cmd = API_CMD_SET_FOCUS;
        else if (strcmp(command, "minimize") == 0) api_cmd = API_CMD_MINIMIZE;
        else if (strcmp(command, "maximize") == 0) api_cmd = API_CMD_MAXIMIZE;
        else if (strcmp(command, "close") == 0) api_cmd = API_CMD_CLOSE;
        else {
            print_usage(argv[0]);
            XCloseDisplay(dpy);
            return 1;
        }
        send_api_command(dpy, root, self, api_cmd, (long)win_id);
    }

    XCloseDisplay(dpy);
    return 0;
}
