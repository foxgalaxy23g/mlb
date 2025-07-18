#include "wm.h"

// --- Определения глобальных переменных ---
Display *dpy; // Указатель на дисплей X-сервера
Window root; // ID корневого окна
XWindowAttributes attr; // Атрибуты окна (используются для сохранения состояния при максимизации)
XButtonEvent start; // Событие нажатия кнопки мыши (для перетаскивания/изменения размера)
int screen_width, screen_height; // Размеры экрана
unsigned long border_color, title_bg_color, close_button_color, maximize_button_color, minimize_button_color; // Цвета для оформления
Atom net_wm_state, net_wm_state_fullscreen, api_atom, api_response_atom; // Атомы для протоколов EWMH и API
Atom net_wm_name, net_wm_icon; // Атомы для имени и иконки окна
XFontStruct *title_font; // Структура шрифта для заголовка окна
Client *clients = NULL; // Указатель на начало списка клиентов (окон)
Client *current_client = NULL; // Указатель на текущее активное окно
int first_window_mapped = 0; // Флаг для первого отображаемого окна

// Прототипы функций обработки ошибок X (определены в events.c)
int on_x_error(Display *d, XErrorEvent *e);
int on_io_error(Display *d);

int main(void) {
    XEvent e; // Переменная для хранения событий X-сервера

    // Открываем соединение с X-сервером
    if (!(dpy = XOpenDisplay(NULL))) {
        fprintf(stderr, "tinywm-plus: Не удалось открыть дисплей.\n");
        return 1;
    }
    root = DefaultRootWindow(dpy); // Получаем ID корневого окна

    // Инициализация размеров экрана
    screen_width = DisplayWidth(dpy, DefaultScreen(dpy));
    screen_height = DisplayHeight(dpy, DefaultScreen(dpy));

    // Инициализация цветов
    border_color = get_color("#444444");
    title_bg_color = get_color("#333333");
    close_button_color = get_color("#ff6666");
    maximize_button_color = get_color("#66ff66");
    minimize_button_color = get_color("#66ccff");
    
    // Инициализация атомов EWMH и API
    net_wm_state = XInternAtom(dpy, "_NET_WM_STATE", False);
    net_wm_state_fullscreen = XInternAtom(dpy, "_NET_WM_STATE_FULLSCREEN", False);
    api_atom = XInternAtom(dpy, "_TINYWM_PLUS_API", False);
    api_response_atom = XInternAtom(dpy, "_TINYWM_PLUS_API_RESPONSE", False);
    net_wm_name = XInternAtom(dpy, "_NET_WM_NAME", False); // Инициализация атома для имени окна
    net_wm_icon = XInternAtom(dpy, "_NET_WM_ICON", False); // Инициализация атома для иконки окна

    // Загрузка шрифта для заголовка
    title_font = XLoadQueryFont(dpy, "-*-helvetica-bold-r-*-*-12-*-*-*-*-*-*-*");
    if (!title_font) {
        fprintf(stderr, "tinywm-plus: Не удалось загрузить шрифт, использую шрифт по умолчанию.\n");
        title_font = XLoadQueryFont(dpy, "fixed"); // Резервный шрифт
    }

    // Установка обработчиков ошибок Xlib
    XSetErrorHandler(on_x_error);
    XSetIOErrorHandler(on_io_error);

    // Получаем атрибуты корневого окна
    XGetWindowAttributes(dpy, root, &attr);
    // Выбираем события для корневого окна:
    // SubstructureRedirectMask: для перенаправления запросов на отображение/изменение окон
    // SubstructureNotifyMask: для уведомлений об изменениях в поддеревьях (например, уничтожение окон)
    // PropertyChangeMask: для отслеживания изменений свойств окон (например, имени)
    XSelectInput(dpy, root, SubstructureRedirectMask | SubstructureNotifyMask | PropertyChangeMask);

    // Захват клавиш для глобальных горячих клавиш
    XGrabKey(dpy, XKeysymToKeycode(dpy, XK_F4), Mod1Mask, root, True, GrabModeAsync, GrabModeAsync);
    XGrabKey(dpy, XKeysymToKeycode(dpy, XK_Tab), Mod1Mask, root, True, GrabModeAsync, GrabModeAsync);
    XGrabKey(dpy, XKeysymToKeycode(dpy, XK_T), ControlMask | Mod1Mask, root, True, GrabModeAsync, GrabModeAsync);
    XGrabKey(dpy, XKeysymToKeycode(dpy, XK_F11), ControlMask | Mod1Mask, root, True, GrabModeAsync, GrabModeAsync);
    XGrabKey(dpy, XKeysymToKeycode(dpy, XK_W), ControlMask | Mod1Mask, root, True, GrabModeAsync, GrabModeAsync);

    start.subwindow = None; // Инициализация для перетаскивания

    // Главный цикл событий
    for (;;) {
        XNextEvent(dpy, &e); // Получаем следующее событие
        switch (e.type) {
            case KeyPress:         on_key_press(&e); break; // Нажатие клавиши
            case ButtonPress:      on_button_press(&e); break; // Нажатие кнопки мыши
            case MotionNotify:     on_motion_notify(&e); break; // Движение мыши
            case ButtonRelease:    on_button_release(&e); break; // Отпускание кнопки мыши
            case MapRequest:       on_map_request(&e); break; // Запрос на отображение окна
            case DestroyNotify:    on_destroy_notify(&e); break; // Уничтожение окна
            case UnmapNotify:      on_unmap_notify(&e); break; // Скрытие окна
            case ConfigureRequest: on_configure_request(&e); break; // Запрос на изменение конфигурации окна
            case Expose:           on_expose(&e); break; // Событие отрисовки (окно нуждается в перерисовке)
            case ClientMessage:    on_client_message(&e); break; // Клиентское сообщение (для API)
            case PropertyNotify:   on_property_notify(&e); break; // Изменение свойства окна (например, имени)
        }
    }

    XFreeFont(dpy, title_font); // Освобождаем загруженный шрифт
    XCloseDisplay(dpy); // Закрываем соединение с X-сервером
    return 0;
}
