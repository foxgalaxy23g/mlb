#include "wm.h"

// Создает фрейм (рамку) для нового окна
void frame(Window w) {
    XWindowAttributes wattr;
    if (!XGetWindowAttributes(dpy, w, &wattr)) return; // Получаем атрибуты окна

    // Создаем новое окно-фрейм
    Window frame = XCreateSimpleWindow(dpy, root, wattr.x, wattr.y,
                                       wattr.width + 2 * BORDER_WIDTH,
                                       wattr.height + TITLE_HEIGHT + BORDER_WIDTH,
                                       BORDER_WIDTH, border_color, title_bg_color);

    // Выбираем события для фрейма
    XSelectInput(dpy, frame, SubstructureRedirectMask | SubstructureNotifyMask |
                               ButtonPressMask | ButtonReleaseMask | PointerMotionMask | ExposureMask);

    // Перемещаем окно приложения внутрь фрейма
    XReparentWindow(dpy, w, frame, BORDER_WIDTH, TITLE_HEIGHT);

    // Отображаем фрейм и окно приложения
    XMapWindow(dpy, frame);
    XMapWindow(dpy, w);

    add_client(frame, w); // Добавляем нового клиента в список (функция из client.c)

    XRaiseWindow(dpy, frame); // Поднимаем фрейм наверх
    XSetInputFocus(dpy, w, RevertToParent, CurrentTime); // Устанавливаем фокус на окно приложения
}

// Удаляет фрейм окна
void unframe(Window w) {
    Client *c = find_client_by_win(w); // Находим клиента по окну приложения
    if (!c) return;

    XReparentWindow(dpy, w, root, 0, 0); // Возвращаем окно приложения на корневое окно
    XUnmapWindow(dpy, c->frame); // Скрываем фрейм
    XDestroyWindow(dpy, c->frame); // Уничтожаем фрейм
    remove_client(c); // Удаляем клиента из списка (функция из client.c)
}

// Закрывает клиентское окно
void close_client(Client *c) {
    XEvent ev;
    memset(&ev, 0, sizeof(ev)); // Обнуляем структуру события
    ev.xclient.type = ClientMessage;
    ev.xclient.window = c->win;
    ev.xclient.message_type = XInternAtom(dpy, "WM_PROTOCOLS", True); // Протокол WM_PROTOCOLS
    ev.xclient.format = 32;
    ev.xclient.data.l[0] = XInternAtom(dpy, "WM_DELETE_WINDOW", False); // Сообщение WM_DELETE_WINDOW
    ev.xclient.data.l[1] = CurrentTime;

    // Отправляем сообщение окну, чтобы оно закрылось самостоятельно
    XSendEvent(dpy, c->win, False, NoEventMask, &ev);
}

// Переключает фокус между клиентскими окнами
void cycle_clients() {
    if (!clients) return; // Если нет клиентов, выходим
    // Если только одно свернутое окно, не переключаемся
    if (!clients->next && clients->is_minimized) return;

    Client *start_client = current_client ? current_client : clients; // Начинаем с текущего или первого
    Client *next = start_client;

    // Ищем следующее несвернутое окно
    do {
        next = next->next ? next->next : clients; // Переходим к следующему или к началу списка
    } while (next->is_minimized && next != start_client); // Продолжаем, пока не найдем несвернутое или не вернемся к стартовому

    if (!next->is_minimized) { // Если нашли несвернутое окно
        current_client = next; // Устанавливаем его как текущее
        XRaiseWindow(dpy, current_client->frame); // Поднимаем его фрейм наверх
        XSetInputFocus(dpy, current_client->win, RevertToParent, CurrentTime); // Устанавливаем фокус
    }
}

// Сворачивает клиентское окно
void minimize_client(Client *c) {
    if (!c || c->is_minimized) return; // Если окно уже свернуто или не существует, выходим
    c->is_minimized = 1; // Устанавливаем флаг свернутого состояния
    XUnmapWindow(dpy, c->frame); // Скрываем фрейм окна
    if (c == current_client) cycle_clients(); // Если свернули текущее окно, переключаемся на другое
}

// Разворачивает все свернутые окна
void unminimize_all() {
    Client *c, *last = NULL;
    for (c = clients; c; c = c->next) { // Проходим по всем клиентам
        if (c->is_minimized) { // Если окно свернуто
            c->is_minimized = 0; // Снимаем флаг свернутого состояния
            XMapWindow(dpy, c->frame); // Отображаем фрейм окна
            last = c; // Запоминаем последнее развернутое окно
        }
    }
    if (last) { // Если было развернуто хотя бы одно окно
        current_client = last; // Делаем последнее развернутое окно текущим
        XRaiseWindow(dpy, current_client->frame); // Поднимаем его фрейм наверх
        XSetInputFocus(dpy, current_client->win, RevertToParent, CurrentTime); // Устанавливаем фокус
    }
}

// Переключает состояние максимального размера окна
void toggle_maximize(Client *c) {
    if (c->is_fullscreen || c->is_minimized) return; // Не максимизируем, если в полноэкранном или свернутом режиме

    if (c->is_maximized) { // Если окно уже максимизировано
        // Восстанавливаем предыдущие размеры и позицию
        XMoveResizeWindow(dpy, c->frame, c->x, c->y, c->w, c->h);
        XResizeWindow(dpy, c->win, c->w - 2 * BORDER_WIDTH, c->h - TITLE_HEIGHT - BORDER_WIDTH);
        c->is_maximized = 0; // Снимаем флаг максимизации
    } else { // Если окно не максимизировано
        XGetWindowAttributes(dpy, c->frame, &attr); // Сохраняем текущие атрибуты окна
        c->x = attr.x; c->y = attr.y; c->w = attr.width; c->h = attr.height; // Сохраняем позицию и размер
        // Перемещаем и изменяем размер окна на весь экран
        XMoveResizeWindow(dpy, c->frame, 0, 0, screen_width, screen_height);
        XResizeWindow(dpy, c->win, screen_width - 2 * BORDER_WIDTH, screen_height - TITLE_HEIGHT - BORDER_WIDTH);
        c->is_maximized = 1; // Устанавливаем флаг максимизации
    }
}

// Переключает полноэкранный режим окна
void toggle_fullscreen(Client *c) {
    if (c->is_minimized) return; // Не переключаем, если окно свернуто

    if (c->is_fullscreen) { // Если окно в полноэкранном режиме
        // Отменяем полноэкранный режим
        XChangeProperty(dpy, c->win, net_wm_state, XA_ATOM, 32, PropModeReplace, (unsigned char *)0, 0);
        XSetWindowBorderWidth(dpy, c->frame, BORDER_WIDTH); // Восстанавливаем ширину рамки
        c->is_fullscreen = 0; // Снимаем флаг полноэкранного режима

        if (c->is_maximized) { // Если окно было максимизировано до полноэкранного режима
            // Восстанавливаем максимизированное состояние
            XMoveResizeWindow(dpy, c->frame, 0, 0, screen_width, screen_height);
            XMoveResizeWindow(dpy, c->win, BORDER_WIDTH, TITLE_HEIGHT, screen_width - 2 * BORDER_WIDTH, screen_height - TITLE_HEIGHT - BORDER_WIDTH);
        } else { // Если окно не было максимизировано
            // Восстанавливаем предыдущие размеры и позицию
            XMoveResizeWindow(dpy, c->frame, c->x, c->y, c->w, c->h);
            XMoveResizeWindow(dpy, c->win, BORDER_WIDTH, TITLE_HEIGHT, c->w - 2 * BORDER_WIDTH, c->h - TITLE_HEIGHT - BORDER_WIDTH);
        }
    } else { // Если окно не в полноэкранном режиме
        // Устанавливаем полноэкранный режим
        XChangeProperty(dpy, c->win, net_wm_state, XA_ATOM, 32, PropModeReplace, (unsigned char *)&net_wm_state_fullscreen, 1);
        if (!c->is_maximized) { // Если окно не было максимизировано, сохраняем его текущие размеры
            XGetWindowAttributes(dpy, c->frame, &attr);
            c->x = attr.x; c->y = attr.y; c->w = attr.width; c->h = attr.height;
        }
        c->is_fullscreen = 1; // Устанавливаем флаг полноэкранного режима
        XSetWindowBorderWidth(dpy, c->frame, 0); // Убираем рамку
        XMoveResizeWindow(dpy, c->frame, 0, 0, screen_width, screen_height); // Растягиваем на весь экран
        XMoveResizeWindow(dpy, c->win, 0, 0, screen_width, screen_height); // Растягиваем окно приложения на весь экран
    }
    XRaiseWindow(dpy, c->frame); // Поднимаем фрейм наверх
}

// Получает пиксельное значение цвета по его имени (строке)
unsigned long get_color(const char *color_name) {
    Colormap cmap = DefaultColormap(dpy, DefaultScreen(dpy)); // Получаем карту цветов
    XColor color;
    XAllocNamedColor(dpy, cmap, color_name, &color, &color); // Выделяем цвет по имени
    return color.pixel; // Возвращаем пиксельное значение цвета
}
