import sys
from PyQt5.QtCore import QUrl, QSize
from PyQt5.QtWidgets import QApplication
from PyQt5.QtWebEngineWidgets import QWebEngineView
import argparse

class SimpleBrowser(QWebEngineView):
    def __init__(self, initial_url="http://www.google.com"):
        super().__init__()
        self.setFixedSize(QSize(512, 256)) # Устанавливаем фиксированный размер окна

        # Устанавливаем начальный URL
        self.setUrl(QUrl(initial_url))

        # Флаг, чтобы заголовок установился только один раз
        self._initial_title_set = False

        # Привязываем метод к сигналу загрузки страницы
        self.loadFinished.connect(self._on_load_finished)


    def _on_load_finished(self, ok):
        # Если страница успешно загружена и заголовок еще не установлен
        if ok and not self._initial_title_set:
            # Устанавливаем заголовок окна равным заголовку загруженной страницы
            self.setWindowTitle(self.title())
            # Поднимаем флаг, что заголовок установлен
            self._initial_title_set = True

            # Отключаем обработчик loadFinished, если он больше не нужен для этой цели
            # Это гарантирует, что заголовок не будет меняться при последующих загрузках
            self.loadFinished.disconnect(self._on_load_finished)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Простой микро-браузер на PyQt5.")
    parser.add_argument("--url", default="http://www.google.com",
                        help="Начальный URL для загрузки.")
    args = parser.parse_args()

    app = QApplication(sys.argv)
    browser = SimpleBrowser(args.url)
    browser.show()
    sys.exit(app.exec_())
