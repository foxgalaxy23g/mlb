#include "wm.h"

// Обработчик сообщений API, отправленных другими приложениями
void handle_api_message(XClientMessageEvent *ev) {
    long cmd = ev->data.l[0]; // Команда API
    Window requestor = (Window)ev->data.l[2]; // Окно-отправитель запроса
    Window target_win = (Window)ev->data.l[1]; // Целевое окно (если применимо)
    Client *c = find_client_by_win(target_win); // Находим клиента по целевому окну

    switch (cmd) {
        case API_CMD_LIST_WINDOWS: { // Команда: перечислить все окна
            XEvent response;
            memset(&response, 0, sizeof(response)); // Обнуляем структуру ответа
            response.xclient.type = ClientMessage;
            response.xclient.display = dpy;
            response.xclient.window = requestor; // Отправляем ответ окну-отправителю
            response.xclient.message_type = api_atom; // Тип сообщения API
            response.xclient.format = 32;

            // Проходим по всем клиентам и отправляем информацию о каждом
            for (Client *iter = clients; iter; iter = iter->next) {
                response.xclient.data.l[0] = API_RESPONSE_WINDOW_INFO; // Тип ответа: информация об окне
                response.xclient.data.l[1] = (long)iter->win; // ID окна приложения
                // Кодируем состояние окна (свернуто, максимизировано, полноэкранно) в битовое поле
                int state = (iter->is_minimized << 0) | (iter->is_maximized << 1) | (iter->is_fullscreen << 2);
                response.xclient.data.l[2] = state;
                XSendEvent(dpy, requestor, False, NoEventMask, &response);
            }
            // Отправляем сообщение об окончании списка
            response.xclient.data.l[0] = API_RESPONSE_LIST_END;
            XSendEvent(dpy, requestor, False, NoEventMask, &response);
            break;
        }
        case API_CMD_GET_WINDOW_NAME: { // Команда: получить имя окна
            if (c) { // Если клиент найден
                // Используем имя, которое уже хранится в структуре клиента
                if (c->name) {
                    XChangeProperty(dpy, requestor, api_response_atom, XA_STRING, 8, PropModeReplace, (unsigned char*)c->name, strlen(c->name));
                } else { // Если имя по какой-то причине не было получено ранее, пытаемся получить его сейчас
                    char *name = NULL;
                    if (XFetchName(dpy, c->win, &name) && name) {
                        XChangeProperty(dpy, requestor, api_response_atom, XA_STRING, 8, PropModeReplace, (unsigned char*)name, strlen(name));
                        XFree(name);
                    } else { // Если имя не найдено, отправляем пустую строку
                        XChangeProperty(dpy, requestor, api_response_atom, XA_STRING, 8, PropModeReplace, (unsigned char*)"", 0);
                    }
                }
            }
            break;
        }
        case API_CMD_SET_FOCUS: // Команда: установить фокус на окно
            if (c && !c->is_minimized) { // Если клиент найден и не свернут
                XRaiseWindow(dpy, c->frame); // Поднимаем фрейм наверх
                XSetInputFocus(dpy, c->win, RevertToParent, CurrentTime); // Устанавливаем фокус на окно приложения
                current_client = c; // Делаем его текущим
            }
            break;
        case API_CMD_MINIMIZE: // Команда: свернуть окно
            if (c) minimize_client(c);
            break;
        case API_CMD_MAXIMIZE: // Команда: максимизировать окно
            if (c) toggle_maximize(c);
            break;
        case API_CMD_CLOSE: // Команда: закрыть окно
            if (c) close_client(c);
            break;
    }
}
