import sys
from PyQt5.QtWidgets import QApplication, QMainWindow
from PyQt5.QtWebEngineWidgets import QWebEngineView
from PyQt5.QtCore import QUrl, QSize
from PyQt5.QtGui import QScreen

class WebViewer(QMainWindow):
    def __init__(self):
        super().__init__()
        self.setWindowTitle("Localhost Viewer")

        self.browser = QWebEngineView()
        self.browser.setUrl(QUrl("http://127.0.0.1:7777"))
        self.setCentralWidget(self.browser)

        # Получаем размеры экрана
        screen = QApplication.primaryScreen()
        size = screen.size()

        # Устанавливаем размер окна равный экрану
        self.resize(size.width(), size.height())
        self.move(0, 0)

if __name__ == "__main__":
    app = QApplication(sys.argv)
    viewer = WebViewer()
    viewer.show()  # не fullScreen, просто большое окно
    sys.exit(app.exec_())
