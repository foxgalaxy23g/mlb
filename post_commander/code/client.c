#include "wm.h"

// Находит клиента по ID окна приложения
Client* find_client_by_win(Window w) {
    for (Client *c = clients; c; c = c->next) {
        if (c->win == w) return c;
    }
    return NULL;
}

// Находит клиента по ID окна-фрейма
Client* find_client_by_frame(Window w) {
    for (Client *c = clients; c; c = c->next) {
        if (c->frame == w) return c;
    }
    return NULL;
}

// Добавляет нового клиента в список
void add_client(Window frame, Window win) {
    Client *c = (Client *)malloc(sizeof(Client));
    if (!c) return; // Проверка выделения памяти

    c->frame = frame;
    c->win = win;
    c->next = clients; // Добавляем в начало списка
    c->is_maximized = 0;
    c->is_fullscreen = 0;
    c->is_minimized = 0;
    c->name = NULL; // Инициализация имени окна
    // c->icon = NULL; // Инициализация иконки (пока не используется)
    clients = c; // Обновляем указатель на начало списка
    current_client = c; // Делаем новым текущим клиентом

    update_window_title(c); // Получаем и устанавливаем имя окна
    // update_window_icon(c); // Получаем и устанавливаем иконку окна (пока не реализовано)
}

// Удаляет клиента из списка и освобождает память
void remove_client(Client *c) {
    Client **p;
    // Ищем указатель на текущий клиент в списке
    for (p = &clients; *p && *p != c; p = &(*p)->next);

    if (*p) { // Если клиент найден
        *p = c->next; // Удаляем клиента из списка, перенаправляя указатель
        if (current_client == c) { // Если удаляемый клиент был текущим
            current_client = clients; // Устанавливаем следующий клиент как текущий (или NULL, если список пуст)
        }
        if (c->name) XFree(c->name); // Освобождаем память, выделенную для имени окна
        // if (c->icon) XDestroyImage(c->icon); // Освобождаем память, выделенную для иконки
        free(c); // Освобождаем структуру клиента
    }
}
