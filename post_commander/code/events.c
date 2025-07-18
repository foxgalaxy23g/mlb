#include "wm.h"

// Обработчик события нажатия клавиши
void on_key_press(XEvent *e) {
    // Ctrl+Alt+T: Открыть xterm
    if ((e->xkey.state & (ControlMask | Mod1Mask)) && e->xkey.keycode == XKeysymToKeycode(dpy, XK_T)) {
        if (fork() == 0) { setsid(); execlp("xterm", "xterm", NULL); }
    }
    // Alt+F4: Закрыть текущее окно
    else if ((e->xkey.state & Mod1Mask) && e->xkey.keycode == XKeysymToKeycode(dpy, XK_F4)) {
        if (current_client) close_client(current_client);
    }
    // Alt+Tab: Переключить окно
    else if ((e->xkey.state & Mod1Mask) && e->xkey.keycode == XKeysymToKeycode(dpy, XK_Tab)) {
        cycle_clients();
    }
    // Ctrl+Alt+F11: Переключить полноэкранный режим
    else if ((e->xkey.state & (ControlMask | Mod1Mask)) && e->xkey.keycode == XKeysymToKeycode(dpy, XK_F11)) {
        if (current_client) toggle_fullscreen(current_client);
    }
    // Ctrl+Alt+W: Развернуть все свернутые окна
    else if ((e->xkey.state & (ControlMask | Mod1Mask)) && e->xkey.keycode == XKeysymToKeycode(dpy, XK_W)) {
        unminimize_all();
    }
}

// Обработчик события нажатия кнопки мыши
void on_button_press(XEvent *e) {
    Client *c = find_client_by_frame(e->xbutton.window); // Находим клиента по фрейму
    if (!c) return;

    XRaiseWindow(dpy, c->frame); // Поднимаем фрейм наверх
    XSetInputFocus(dpy, c->win, RevertToParent, CurrentTime); // Устанавливаем фокус
    current_client = c; // Делаем его текущим

    XGetWindowAttributes(dpy, c->frame, &attr); // Получаем атрибуты фрейма для кнопок

    // Вычисляем позиции кнопок
    int close_x = attr.width - BUTTON_SIZE - BUTTON_MARGIN;
    int max_x = close_x - BUTTON_SIZE - BUTTON_MARGIN;
    int min_x = max_x - BUTTON_SIZE - BUTTON_MARGIN;

    // Если нажата левая кнопка мыши в области заголовка
    if (e->xbutton.button == Button1 && e->xbutton.y < TITLE_HEIGHT) {
        if (e->xbutton.x > close_x) { // Нажата кнопка закрытия
            close_client(c);
            return;
        }
        if (e->xbutton.x > max_x && e->xbutton.x < close_x) { // Нажата кнопка максимизации
            toggle_maximize(c);
            return;
        }
        if (e->xbutton.x > min_x && e->xbutton.x < max_x) { // Нажата кнопка минимизации
            minimize_client(c);
            return;
        }
    }
    start = e->xbutton; // Сохраняем начальное событие для перетаскивания/изменения размера
    start.subwindow = c->frame; // Указываем, какой фрейм перетаскивается
}

// Обработчик события движения мыши
void on_motion_notify(XEvent *e) {
    if (start.subwindow == None) return; // Если не было начального нажатия кнопки, выходим

    int xdiff = e->xmotion.x_root - start.x_root; // Разница по X
    int ydiff = e->xmotion.y_root - start.y_root; // Разница по Y

    Client *c = find_client_by_frame(start.subwindow); // Находим клиента по фрейму
    if (!c) return;

    // Если окно было максимизировано или в полноэкранном режиме, возвращаем его в нормальное состояние
    if (c->is_maximized || c->is_fullscreen) {
        c->is_maximized = 0;
        c->is_fullscreen = 0;
        XSetWindowBorderWidth(dpy, c->frame, BORDER_WIDTH);
    }

    if (start.button == Button1) { // Перемещение окна (левая кнопка)
        XMoveWindow(dpy, c->frame, attr.x + xdiff, attr.y + ydiff);
    } else if (start.button == Button3) { // Изменение размера окна (правая кнопка)
        int new_width = attr.width + xdiff;
        int new_height = attr.height + ydiff;
        if (new_width < 100) new_width = 100; // Минимальная ширина
        if (new_height < 50) new_height = 50; // Минимальная высота
        XResizeWindow(dpy, c->frame, new_width, new_height); // Изменяем размер фрейма
        XResizeWindow(dpy, c->win, new_width - 2 * BORDER_WIDTH, new_height - TITLE_HEIGHT - BORDER_WIDTH); // Изменяем размер окна приложения
    }
}

// Обработчик события отпускания кнопки мыши
void on_button_release(XEvent *e) {
    start.subwindow = None; // Сбрасываем флаг перетаскивания/изменения размера
}

// Обработчик события запроса на отображение окна
void on_map_request(XEvent *e) {
    if (!first_window_mapped) { // Если это первое окно, просто отображаем его
        XMapWindow(dpy, e->xmaprequest.window);
        first_window_mapped = 1;
    } else { // Для последующих окон создаем фрейм
        frame(e->xmaprequest.window);
    }
}

// Обработчик события уничтожения окна
void on_destroy_notify(XEvent *e) {
    Client *c = find_client_by_win(e->xdestroywindow.window); // Находим клиента
    if (c) unframe(c->win); // Удаляем фрейм, если клиент найден
}

// Обработчик события скрытия окна (unmap)
void on_unmap_notify(XEvent *e) {
    Client *c = find_client_by_win(e->xunmap.window); // Находим клиента
    if (c) unframe(c->win); // Удаляем фрейм, если клиент найден
}

// Обработчик события запроса на изменение конфигурации окна
void on_configure_request(XEvent *e) {
    XConfigureRequestEvent *ev = &e->xconfigurerequest;
    XWindowChanges wc;
    // Копируем запрошенные изменения
    wc.x = ev->x; wc.y = ev->y; wc.width = ev->width; wc.height = ev->height;
    wc.border_width = ev->border_width; wc.sibling = ev->above; wc.stack_mode = ev->detail;

    Client *c = find_client_by_win(ev->window); // Находим клиента
    if (c) { // Если это наше окно, применяем изменения к фрейму и окну приложения
        XConfigureWindow(dpy, c->frame, ev->value_mask, &wc);
        XConfigureWindow(dpy, c->win, ev->value_mask, &wc);
    } else { // Иначе, применяем изменения напрямую к окну
        XConfigureWindow(dpy, ev->window, ev->value_mask, &wc);
    }
}

// Функция для получения имени окна
void update_window_title(Client *c) {
    if (!c) return;

    char *new_name = NULL;
    char *prop_net_wm_name = NULL; // Здесь будут храниться данные из XGetWindowProperty
    char *prop_wm_name = NULL;     // Здесь будут храниться данные из XFetchName

    Atom actual_type;
    int actual_format;
    unsigned long nitems, bytes_after;

    // 1. Попытка получить _NET_WM_NAME (UTF8_STRING или STRING)
    Atom utf8_string = XInternAtom(dpy, "UTF8_STRING", False);
    if (XGetWindowProperty(dpy, c->win, net_wm_name, 0, ~0L, False, AnyPropertyType,
                           &actual_type, &actual_format, &nitems, &bytes_after,
                           (unsigned char**)&prop_net_wm_name) == Success && prop_net_wm_name) {
        if (actual_type == utf8_string || actual_type == XA_STRING) {
            new_name = strdup(prop_net_wm_name);
        }
        XFree(prop_net_wm_name); // Всегда освобождаем prop_net_wm_name, если XGetWindowProperty вернул данные
    }

    // 2. Если _NET_WM_NAME не удалось получить или это не строка, пытаемся получить WM_NAME
    if (!new_name) {
        if (XFetchName(dpy, c->win, &prop_wm_name) && prop_wm_name) {
            new_name = strdup(prop_wm_name);
            XFree(prop_wm_name); // Всегда освобождаем prop_wm_name, если XFetchName вернул данные
        }
    }

    // 3. Если оба метода не дали результата, используем имя по умолчанию
    if (!new_name) {
        new_name = strdup("Неизвестно");
    }

    // Освобождаем старое имя и присваиваем новое
    if (c->name) {
        free(c->name); // Используем free для памяти, выделенной strdup
    }
    c->name = new_name;

    draw_title_bar(c); // Перерисовать заголовок после обновления имени
}

// Заглушка для иконки (реализация сложнее)
void update_window_icon(Client *c) {
    // В реальной реализации здесь нужно получать _NET_WM_ICON,
    // преобразовывать его в XImage и сохранять в c->icon.
    // Это требует обработки различных форматов пикселей и глубины.
    // Пока оставляем пустым.
    // draw_title_bar(c); // Перерисовать заголовок после обновления иконки
}

// Функция отрисовки всего заголовка
void draw_title_bar(Client *c) {
    if (!c || c->is_fullscreen) return;

    XWindowAttributes frame_attr;
    XGetWindowAttributes(dpy, c->frame, &frame_attr);

    // Очистка заголовка
    XSetForeground(dpy, DefaultGC(dpy, 0), title_bg_color);
    XFillRectangle(dpy, c->frame, DefaultGC(dpy, 0), 0, 0, frame_attr.width, TITLE_HEIGHT);

    // Отрисовка кнопок
    int close_x = frame_attr.width - BUTTON_SIZE - BUTTON_MARGIN;
    XSetForeground(dpy, DefaultGC(dpy, 0), close_button_color);
    XFillRectangle(dpy, c->frame, DefaultGC(dpy, 0), close_x, BUTTON_MARGIN, BUTTON_SIZE, BUTTON_SIZE);

    int max_x = close_x - BUTTON_SIZE - BUTTON_MARGIN;
    XSetForeground(dpy, DefaultGC(dpy, 0), maximize_button_color);
    XFillRectangle(dpy, c->frame, DefaultGC(dpy, 0), max_x, BUTTON_MARGIN, BUTTON_SIZE, BUTTON_SIZE);

    int min_x = max_x - BUTTON_SIZE - BUTTON_MARGIN;
    XSetForeground(dpy, DefaultGC(dpy, 0), minimize_button_color);
    XFillRectangle(dpy, c->frame, DefaultGC(dpy, 0), min_x, BUTTON_MARGIN, BUTTON_SIZE, BUTTON_SIZE);

    // Отрисовка имени окна
    if (c->name && title_font) {
        XSetFont(dpy, DefaultGC(dpy, 0), title_font->fid);
        XSetForeground(dpy, DefaultGC(dpy, 0), get_color("#FFFFFF")); // Белый цвет для текста
        int text_x = BUTTON_MARGIN; // Начальная позиция текста
        int text_y = (TITLE_HEIGHT / 2) + (title_font->ascent / 2); // Центрирование по вертикали

        // Если иконка будет реализована, нужно будет сместить текст
        // if (c->icon) {
        //     text_x += c->icon_width + BUTTON_MARGIN;
        // }

        // Обрезка текста, если он слишком длинный
        int max_text_width = min_x - BUTTON_MARGIN - text_x;
        if (max_text_width > 0) {
            char *display_name = c->name;
            int name_len = strlen(c->name);
            int text_width = XTextWidth(title_font, display_name, name_len);

            if (text_width > max_text_width) {
                // Если текст слишком длинный, обрезаем и добавляем "..."
                char buffer[256]; // Буфер для обрезанного имени
                int current_len = 0;
                for (int i = 0; i < name_len; ++i) {
                    // Проверяем, поместится ли текущий кусок текста плюс "..."
                    if (XTextWidth(title_font, display_name, i + 1) + XTextWidth(title_font, "...", 3) > max_text_width) {
                        current_len = i;
                        break;
                    }
                    current_len = i + 1;
                }
                strncpy(buffer, display_name, current_len);
                buffer[current_len] = '\0';
                strcat(buffer, "...");
                XDrawString(dpy, c->frame, DefaultGC(dpy, 0), text_x, text_y, buffer, strlen(buffer));
            } else {
                XDrawString(dpy, c->frame, DefaultGC(dpy, 0), text_x, text_y, display_name, name_len);
            }
        }
    }

    // Отрисовка иконки (заглушка)
    // if (c->icon) {
    //     XPutImage(dpy, c->frame, DefaultGC(dpy, 0), c->icon, 0, 0, BUTTON_MARGIN, (TITLE_HEIGHT - c->icon_height) / 2, c->icon_width, c->icon_height);
    // }
}

// Обработчик события отрисовки (expose)
void on_expose(XEvent *e) {
    Client *c = find_client_by_frame(e->xexpose.window); // Находим клиента по фрейму
    if (c) { // Рисуем заголовок только если это наш фрейм
        draw_title_bar(c);
    }
}

// Обработчик события клиентского сообщения (для API)
void on_client_message(XEvent *e) {
    if (e->xclient.message_type == api_atom) { // Если это сообщение нашего API
        handle_api_message(&e->xclient); // Обрабатываем его
    }
}

// Обработчик события изменения свойства окна
void on_property_notify(XEvent *e) {
    XPropertyEvent *ev = &e->xproperty;
    Client *c = find_client_by_win(ev->window); // Находим клиента по окну
    if (!c) return;

    // Если изменилось свойство _NET_WM_NAME или WM_NAME, обновляем заголовок
    if (ev->atom == net_wm_name || ev->atom == XA_WM_NAME) {
        update_window_title(c);
    }
    // else if (ev->atom == net_wm_icon) {
    //     update_window_icon(c); // Если будет реализована иконка
    // }
}

// Обработчик ошибок Xlib
int on_x_error(Display *d, XErrorEvent *e) {
    char error_text[1024];
    XGetErrorText(d, e->error_code, error_text, sizeof(error_text));
    fprintf(stderr, "tinywm-plus: X error: %s (request code: %d)\n", error_text, e->request_code);
    return 0; // Возвращаем 0, чтобы продолжить выполнение
}

// Обработчик ошибок ввода/вывода Xlib (потеря соединения с X-сервером)
int on_io_error(Display *d) {
    fprintf(stderr, "tinywm-plus: X server connection lost.\n");
    return 1; // Возвращаем 1, чтобы завершить программу
}
