#include <X11/Xlib.h>
#include <X11/Xatom.h>
#include <X11/keysym.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>

// --- КОНСТАНТЫ ---
#define TITLE_HEIGHT 24
#define BORDER_WIDTH 2
#define BUTTON_SIZE 18
#define RESIZE_CORNER_SIZE 16

// --- Структура клиента ---
typedef struct Client Client;
struct Client {
    Window frame;
    Window client;
    Window btn_close;
    Window btn_maximize;
    Window btn_minimize;

    int x, y, width, height; 
    int is_fullscreen;
};

// --- ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ---
Display *dpy;
Window root;
Client *clients[100];
int client_count = 0;
int current_client_idx = 0; // Для Alt+Tab

XButtonEvent start_event; // Для перемещения/ресайза

// --- ОБЪЯВЛЕНИЕ ФУНКЦИЙ ---
void draw_decorations(Client *c);
void relayout_frame(Client *c);
Client* find_client_by_any(Window win);

// --- ФУНКЦИИ ---

Client* find_client_by_any(Window win) {
    for (int i = 0; i < client_count; i++) {
        if (clients[i]->frame == win || clients[i]->client == win ||
            clients[i]->btn_close == win || clients[i]->btn_maximize == win ||
            clients[i]->btn_minimize == win) {
            return clients[i];
        }
    }
    return NULL;
}

void move_client_to_top(Client* c) {
    int i;
    for (i = 0; i < client_count; ++i) {
        if (clients[i] == c) break;
    }
    if (i >= client_count - 1) return; // Уже на вершине или не найден

    Client* temp = clients[i];
    for (int j = i; j < client_count - 1; ++j) {
        clients[j] = clients[j + 1];
    }
    clients[client_count - 1] = temp;
}

// ИСПРАВЛЕННАЯ И БЕЗОПАСНАЯ ВЕРСИЯ
void remove_client(Client* c) {
    int i;
    for (i = 0; i < client_count; i++) {
        if (clients[i] == c) break;
    }
    if (i == client_count) return;

    XDestroyWindow(dpy, c->btn_close);
    XDestroyWindow(dpy, c->btn_maximize);
    XDestroyWindow(dpy, c->btn_minimize);
    XDestroyWindow(dpy, c->frame);
    
    free(c);

    for (int j = i; j < client_count - 1; j++) {
        clients[j] = clients[j + 1];
    }
    client_count--;

    // КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ: Предотвращаем выход индекса за границы
    if (current_client_idx >= client_count) {
        current_client_idx = client_count > 0 ? client_count - 1 : 0;
    }

    printf("Окно закрыто. Осталось окон: %d\n", client_count);
}

void set_focus(Client* c) {
    if (!c) return;
    XSetInputFocus(dpy, c->client, RevertToParent, CurrentTime);
    
    Client* old_focused = (client_count > 0) ? clients[client_count - 1] : NULL;
    move_client_to_top(c);
    
    // Перерисовываем старое и новое окна для смены их вида (активное/неактивное)
    if (old_focused && old_focused != c) {
        draw_decorations(old_focused);
    }
    draw_decorations(c);
}

void toggle_fullscreen(Client* c) {
    XWindowAttributes frame_attr;
    XGetWindowAttributes(dpy, c->frame, &frame_attr);

    if (c->is_fullscreen) {
        XMoveResizeWindow(dpy, c->frame, c->x, c->y, c->width, c->height);
        c->is_fullscreen = 0;
    } else {
        c->x = frame_attr.x; c->y = frame_attr.y; 
        c->width = frame_attr.width; c->height = frame_attr.height;

        int screen_width = XWidthOfScreen(DefaultScreenOfDisplay(dpy));
        int screen_height = XHeightOfScreen(DefaultScreenOfDisplay(dpy));
        
        XMoveResizeWindow(dpy, c->frame, 0, 0, screen_width, screen_height);
        c->is_fullscreen = 1;
    }
    relayout_frame(c);
}

void relayout_frame(Client *c) {
    if (!c) return;

    XWindowAttributes frame_attr;
    XGetWindowAttributes(dpy, c->frame, &frame_attr);

    if (c->is_fullscreen) {
        XMoveResizeWindow(dpy, c->client, 0, 0, frame_attr.width, frame_attr.height);
        XUnmapWindow(dpy, c->btn_close);
        XUnmapWindow(dpy, c->btn_maximize);
        XUnmapWindow(dpy, c->btn_minimize);
    } else {
        int btn_y = (TITLE_HEIGHT - BUTTON_SIZE) / 2;
        XMoveWindow(dpy, c->btn_close, frame_attr.width - BUTTON_SIZE - BORDER_WIDTH - 5, btn_y);
        XMoveWindow(dpy, c->btn_maximize, frame_attr.width - 2 * BUTTON_SIZE - BORDER_WIDTH - 10, btn_y);
        XMoveWindow(dpy, c->btn_minimize, frame_attr.width - 3 * BUTTON_SIZE - BORDER_WIDTH - 15, btn_y);

        XMapWindow(dpy, c->btn_close);
        XMapWindow(dpy, c->btn_maximize);
        XMapWindow(dpy, c->btn_minimize);

        XResizeWindow(dpy, c->client,
                      frame_attr.width - (2 * BORDER_WIDTH),
                      frame_attr.height - TITLE_HEIGHT - BORDER_WIDTH);
    }
    draw_decorations(c);
}

void draw_decorations(Client *c) {
    if (!c) return;
    XWindowAttributes frame_attr;
    XGetWindowAttributes(dpy, c->frame, &frame_attr);
    int is_active = (client_count > 0 && clients[client_count - 1] == c);
    
    char *window_name;
    if (!XFetchName(dpy, c->client, &window_name)) {
        window_name = "Untitled";
    }

    GC gc = XCreateGC(dpy, c->frame, 0, NULL);
    unsigned long title_color = is_active ? 0x3a5a72 : 0x333333;
    unsigned long border_color = is_active ? 0x3a5a72 : 0x000000;
    
    XSetWindowBorder(dpy, c->frame, border_color);

    XSetForeground(dpy, gc, title_color);
    XFillRectangle(dpy, c->frame, gc, 0, 0, frame_attr.width, TITLE_HEIGHT);

    XSetForeground(dpy, gc, 0xFFFFFF);
    XDrawString(dpy, c->frame, gc, BORDER_WIDTH + 10, (TITLE_HEIGHT / 2) + 5, window_name, strlen(window_name));

    XSetForeground(dpy, gc, 0xFF0000); XFillRectangle(dpy, c->btn_close, gc, 0, 0, BUTTON_SIZE, BUTTON_SIZE);
    XSetForeground(dpy, gc, 0x00FF00); XFillRectangle(dpy, c->btn_maximize, gc, 0, 0, BUTTON_SIZE, BUTTON_SIZE);
    XSetForeground(dpy, gc, 0xFFFF00); XFillRectangle(dpy, c->btn_minimize, gc, 0, 0, BUTTON_SIZE, BUTTON_SIZE);
    
    XFreeGC(dpy, gc);
    XFree(window_name);
}

void manage_window(Window w) {
    XWindowAttributes w_attr;
    XGetWindowAttributes(dpy, w, &w_attr);
    Window frame = XCreateSimpleWindow(dpy, root, w_attr.x, w_attr.y,
        w_attr.width + (2 * BORDER_WIDTH), w_attr.height + TITLE_HEIGHT + BORDER_WIDTH,
        BORDER_WIDTH, 0, 0xcccccc);
        
    int btn_y = (TITLE_HEIGHT - BUTTON_SIZE) / 2;
    Window close_btn = XCreateSimpleWindow(dpy, frame, 0, btn_y, BUTTON_SIZE, BUTTON_SIZE, 0, 0, 0);
    Window max_btn = XCreateSimpleWindow(dpy, frame, 0, btn_y, BUTTON_SIZE, BUTTON_SIZE, 0, 0, 0);
    Window min_btn = XCreateSimpleWindow(dpy, frame, 0, btn_y, BUTTON_SIZE, BUTTON_SIZE, 0, 0, 0);

    XReparentWindow(dpy, w, frame, BORDER_WIDTH, TITLE_HEIGHT);
    XSelectInput(dpy, frame, SubstructureRedirectMask | SubstructureNotifyMask | ButtonPressMask | ButtonReleaseMask | ExposureMask);
    XSelectInput(dpy, w, EnterWindowMask);
    XSelectInput(dpy, close_btn, ButtonPressMask);
    XSelectInput(dpy, max_btn, ButtonPressMask);
    XSelectInput(dpy, min_btn, ButtonPressMask);

    XMapWindow(dpy, w); XMapWindow(dpy, close_btn); XMapWindow(dpy, max_btn); XMapWindow(dpy, min_btn); XMapWindow(dpy, frame);
    
    Client *new_client = (Client*)malloc(sizeof(Client));
    new_client->client = w; new_client->frame = frame; new_client->btn_close = close_btn;
    new_client->btn_maximize = max_btn; new_client->btn_minimize = min_btn; new_client->is_fullscreen = 0;
    
    clients[client_count++] = new_client;
    relayout_frame(new_client);
    set_focus(new_client);
}

void send_close_event(Window win) {
    XEvent ev;
    Atom wm_delete_window = XInternAtom(dpy, "WM_DELETE_WINDOW", False);
    memset(&ev, 0, sizeof(ev));
    ev.type = ClientMessage;
    ev.xclient.window = win;
    ev.xclient.message_type = XInternAtom(dpy, "WM_PROTOCOLS", False);
    ev.xclient.format = 32;
    ev.xclient.data.l[0] = wm_delete_window;
    ev.xclient.data.l[1] = CurrentTime;
    XSendEvent(dpy, win, False, NoEventMask, &ev);
}

// --- ОСНОВНОЙ ЦИКЛ ПРОГРАММЫ ---
int main() {
    if (!(dpy = XOpenDisplay(NULL))) exit(1);
    root = DefaultRootWindow(dpy);
    XSelectInput(dpy, root, SubstructureRedirectMask);

    // --- Горячие клавиши ---
    XGrabKey(dpy, XKeysymToKeycode(dpy, XStringToKeysym("T")), ControlMask | Mod1Mask, root, True, GrabModeAsync, GrabModeAsync);
    XGrabKey(dpy, XKeysymToKeycode(dpy, XStringToKeysym("F4")), Mod1Mask, root, True, GrabModeAsync, GrabModeAsync);
    XGrabKey(dpy, XKeysymToKeycode(dpy, XStringToKeysym("Tab")), Mod1Mask, root, True, GrabModeAsync, GrabModeAsync);
    
    // Глобальный перехват мыши для Alt+Click
    XGrabButton(dpy, 1, Mod1Mask, root, True, ButtonPressMask | ButtonReleaseMask | PointerMotionMask, GrabModeAsync, GrabModeAsync, None, None);
    XGrabButton(dpy, 3, Mod1Mask, root, True, ButtonPressMask | ButtonReleaseMask | PointerMotionMask, GrabModeAsync, GrabModeAsync, None, None);

    start_event.subwindow = None;
    printf("Стабильный оконный менеджер запущен.\n");

    XEvent ev;
    while (1) {
        XNextEvent(dpy, &ev);

        switch (ev.type) {
            case MapRequest:
                manage_window(ev.xmaprequest.window);
                break;
            case DestroyNotify:
                {
                   Client* c = find_client_by_any(ev.xdestroywindow.window);
                   if (c) remove_client(c);
                }
                break;
            case Expose:
                {
                    Client* c = find_client_by_any(ev.xexpose.window);
                    if(c) draw_decorations(c);
                }
                break;
            case KeyPress:
                if (ev.xkey.keycode == XKeysymToKeycode(dpy, XStringToKeysym("Tab"))) {
                    // ИСПРАВЛЕНИЕ: Проверка, что есть что переключать
                    if (client_count > 1) {
                        current_client_idx = (current_client_idx + 1) % client_count;
                        Client* next_client = clients[current_client_idx];
                        XRaiseWindow(dpy, next_client->frame);
                        set_focus(next_client);
                    }
                }
                else if (ev.xkey.keycode == XKeysymToKeycode(dpy, XStringToKeysym("T"))) {
                    if (fork() == 0) { setsid(); execlp("x-terminal-emulator", "x-terminal-emulator", NULL); }
                } 
                else if (ev.xkey.keycode == XKeysymToKeycode(dpy, XStringToKeysym("F4"))) {
                    if (client_count > 0) send_close_event(clients[client_count - 1]->client);
                }
                break;

            /* --- ПОЛНОСТЬЮ ПЕРЕПИСАННАЯ И ИСПРАВЛЕННАЯ ЛОГИКА НАЖАТИЯ МЫШИ --- */
            case ButtonPress:
                {
                    Window target_win = ev.xbutton.subwindow ? ev.xbutton.subwindow : ev.xbutton.window;
                    Client* c = find_client_by_any(target_win);
                    if (!c) break;

                    XRaiseWindow(dpy, c->frame);
                    set_focus(c);

                    if (target_win == c->btn_close) { send_close_event(c->client); break; }
                    if (target_win == c->btn_maximize) { toggle_fullscreen(c); break; }
                    if (target_win == c->btn_minimize) { XUnmapWindow(dpy, c->frame); break; }

                    // Проверяем, был ли клик с Alt или по рамке/заголовку
                    if ((ev.xbutton.state & Mod1Mask) || (ev.xbutton.window == c->frame)) {
                         XWindowAttributes frame_attr;
                         XGetWindowAttributes(dpy, c->frame, &frame_attr);
                         start_event = ev.xbutton;
                         start_event.subwindow = c->frame; // Явно указываем, что двигаем рамку

                         // Если клик был по углу без Alt, притворяемся что это Alt+ПКМ для ресайза
                         if (ev.xbutton.button == 1 && !(ev.xbutton.state & Mod1Mask) &&
                             ev.xbutton.x > frame_attr.width - RESIZE_CORNER_SIZE &&
                             ev.xbutton.y > frame_attr.height - RESIZE_CORNER_SIZE) {
                             
                             start_event.button = 3; // "обман" для MotionNotify
                         }
                    }
                }
                break;
                
            case MotionNotify:
                if (start_event.subwindow != None) {
                    // Пока зажата кнопка, X сам присылает события с нужными координатами
                    int xdiff = ev.xmotion.x_root - start_event.x_root;
                    int ydiff = ev.xmotion.y_root - start_event.y_root;
                    XWindowAttributes frame_attr;
                    XGetWindowAttributes(dpy, start_event.subwindow, &frame_attr);

                    // Alt+ЛКМ или ЛКМ по заголовку
                    if (start_event.button == 1) {
                        XMoveWindow(dpy, start_event.subwindow, frame_attr.x + xdiff, frame_attr.y + ydiff);
                    } 
                    // Alt+ПКМ или ЛКМ по углу
                    else if (start_event.button == 3) {
                         int new_width = frame_attr.width + xdiff;
                         int new_height = frame_attr.height + ydiff;
                         if (new_width < 150) new_width = 150;
                         if (new_height < 100) new_height = 100;
                         
                         Client* c = find_client_by_any(start_event.subwindow);
                         XResizeWindow(dpy, c->frame, new_width, new_height);
                         relayout_frame(c);
                    }
                }
                break;

            case ButtonRelease:
                start_event.subwindow = None;
                break;
        }
    }
    XCloseDisplay(dpy);
    return 0;
}
