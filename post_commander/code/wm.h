#ifndef WM_H
#define WM_H

#include <X11/Xlib.h>
#include <X11/Xatom.h>
#include <X11/Xutil.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <stdio.h>

// --- Константы для оформления ---
#define BORDER_WIDTH 2    // Ширина рамки окна
#define TITLE_HEIGHT 24   // Высота заголовка окна
#define BUTTON_SIZE 18    // Размер кнопок управления (закрыть, максимизировать, свернуть)
#define BUTTON_MARGIN 4   // Отступ для кнопок и текста

// --- Команды API (для взаимодействия с другими приложениями) ---
#define API_CMD_LIST_WINDOWS      1 // Запросить список всех управляемых окон
#define API_CMD_GET_WINDOW_NAME   2 // Запросить имя конкретного окна
#define API_CMD_SET_FOCUS         3 // Установить фокус на конкретное окно
#define API_CMD_MINIMIZE          4 // Свернуть окно
#define API_CMD_MAXIMIZE          5 // Максимизировать/восстановить окно
#define API_CMD_CLOSE             6 // Закрыть окно

// --- Ответы API ---
#define API_RESPONSE_WINDOW_INFO  101 // Информация об одном окне в списке
#define API_RESPONSE_LIST_END     102 // Маркер конца списка окон

// --- Структура клиента (представляет собой управляемое окно) ---
typedef struct Client Client;
struct Client {
    Window frame, win; // ID окна-фрейма (нашего) и окна приложения (клиента)
    Client *next;      // Указатель на следующий клиент в списке
    int x, y, w, h;    // Позиция и размеры окна до максимизации/полноэкранного режима
    int is_maximized, is_fullscreen, is_minimized; // Флаги состояния окна
    char *name;        // Имя окна (заголовок приложения)
    // XImage *icon;    // Для иконки, более сложная реализация (пока не используется)
    // int icon_width, icon_height; // Размеры иконки (пока не используется)
};

// --- Глобальные переменные (объявлены как extern, определяются в main.c) ---
extern Display *dpy; // Указатель на дисплей X-сервера
extern Window root;  // ID корневого окна
extern XWindowAttributes attr; // Атрибуты окна
extern XButtonEvent start; // Событие нажатия кнопки мыши для перетаскивания/изменения размера
extern int screen_width, screen_height; // Размеры экрана
extern unsigned long border_color, title_bg_color, close_button_color, maximize_button_color, minimize_button_color; // Цвета
extern Atom net_wm_state, net_wm_state_fullscreen, api_atom, api_response_atom; // Атомы EWMH и API
extern Atom net_wm_name, net_wm_icon; // Атомы для имени и иконки окна
extern XFontStruct *title_font; // Шрифт для заголовка окна
extern Client *clients; // Указатель на начало списка клиентов
extern Client *current_client; // Указатель на текущий клиент
extern int first_window_mapped; // Флаг для первого отображаемого окна

// --- Прототипы функций (объявлены здесь, определяются в соответствующих .c файлах) ---

// client.c
Client* find_client_by_win(Window w);
Client* find_client_by_frame(Window w);
void add_client(Window frame, Window win);
void remove_client(Client *c);

// actions.c
void frame(Window w);
void unframe(Window w);
void close_client(Client *c);
void minimize_client(Client *c);
void unminimize_all();
void toggle_maximize(Client *c);
void toggle_fullscreen(Client *c);
void cycle_clients();
unsigned long get_color(const char *color_name);

// events.c
void on_key_press(XEvent *e);
void on_button_press(XEvent *e);
void on_motion_notify(XEvent *e);
void on_button_release(XEvent *e);
void on_map_request(XEvent *e);
void on_destroy_notify(XEvent *e);
void on_unmap_notify(XEvent *e);
void on_configure_request(XEvent *e);
void on_expose(XEvent *e);
void on_client_message(XEvent *e);
int on_x_error(Display *d, XErrorEvent *e);
int on_io_error(Display *d);
void on_property_notify(XEvent *e); // Для обработки изменений свойств окна

// api.c
void handle_api_message(XClientMessageEvent *ev);

// Дополнительные функции для отрисовки и обновления (определяются в events.c)
void draw_title_bar(Client *c);
void update_window_title(Client *c);
// void update_window_icon(Client *c); // Пока не реализуем

#endif // WM_H
