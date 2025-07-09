import sys
import os
import configparser
from PyQt5.QtCore import QUrl, Qt
from PyQt5.QtWidgets import (QApplication, QMainWindow, QLineEdit, QToolBar,
                             QAction, QTabWidget, QVBoxLayout, QWidget, QMenu)
from PyQt5.QtGui import QIcon
from PyQt5.QtWebEngineWidgets import QWebEngineView

class Browser(QMainWindow):
    def __init__(self):
        super().__init__()

        self.config = configparser.ConfigParser()
        config_path = os.path.join(sys.path[0], '..', 'config.ini')
        self.config.read(config_path)
        self.search_engine = self.get_search_engine_url()

        self.tabs = QTabWidget()
        self.tabs.setDocumentMode(True)
        self.tabs.tabBarDoubleClicked.connect(self.tab_open_doubleclick)
        self.tabs.currentChanged.connect(self.current_tab_changed)
        self.tabs.setTabsClosable(True)
        self.tabs.tabCloseRequested.connect(self.close_current_tab)
        self.setCentralWidget(self.tabs)

        navtb = QToolBar("Navigation")
        self.addToolBar(Qt.TopToolBarArea, navtb)

        icon_path = lambda name: os.path.join(sys.path[0], 'icons', name)

        back_btn = QAction(QIcon(icon_path("back.png")), "Back", self)
        back_btn.triggered.connect(self.navigate_back)
        navtb.addAction(back_btn)

        forward_btn = QAction(QIcon(icon_path("forward.png")), "Forward", self)
        forward_btn.triggered.connect(self.navigate_forward)
        navtb.addAction(forward_btn)

        reload_btn = QAction(QIcon(icon_path("reload.png")), "Reload", self)
        reload_btn.triggered.connect(self.reload_page)
        navtb.addAction(reload_btn)

        home_btn = QAction(QIcon(icon_path("home.png")), "Home", self)
        home_btn.triggered.connect(self.navigate_home)
        navtb.addAction(home_btn)

        self.url_bar = QLineEdit()
        self.url_bar.returnPressed.connect(self.navigate_to_url)
        navtb.addWidget(self.url_bar)

        new_tab_btn = QAction(QIcon(icon_path("new_tab.png")), "New Tab", self)
        new_tab_btn.triggered.connect(self.add_new_tab)
        navtb.addAction(new_tab_btn)

        # Пуск слева (софт)
        left_start_btn = QAction(QIcon(icon_path("start.png")), "Start", self)
        left_start_btn.triggered.connect(self.show_software_menu)
        navtb.insertAction(back_btn, left_start_btn)

        # Пуск справа (система)
        right_start_btn = QAction(QIcon(icon_path("system.png")), "System", self)
        right_start_btn.triggered.connect(self.show_system_menu)
        navtb.addAction(right_start_btn)

        self.add_new_tab(QUrl.fromLocalFile(os.path.join(sys.path[0], 'start_page.html')), 'Home')

        screen = QApplication.primaryScreen()
        size = screen.size()
        self.setGeometry(0, 0, size.width(), size.height())
        self.show()

    def show_software_menu(self):
        menu = QMenu(self)
        file_path = os.path.join(sys.path[0], 'software.txt')
        if os.path.exists(file_path):
            with open(file_path, 'r', encoding='utf-8') as f:
                for line in f:
                    if ' - ' in line:
                        name, link = line.strip().split(' - ', 1)
                        action = QAction(name, self)
                        action.triggered.connect(lambda _, l=link: self.add_new_tab(QUrl(l), name))
                        menu.addAction(action)
        menu.exec_(self.mapToGlobal(self.pos() + self.rect().topLeft()))

    def show_system_menu(self):
        menu = QMenu(self)

        shutdown_action = QAction("Выключить ПК", self)
        shutdown_action.triggered.connect(lambda: os.system('shutdown /s /t 0' if sys.platform == 'win32' else 'shutdown now'))
        menu.addAction(shutdown_action)

        restart_action = QAction("Перезагрузить ПК", self)
        restart_action.triggered.connect(lambda: os.system('shutdown /r /t 0' if sys.platform == 'win32' else 'reboot'))
        menu.addAction(restart_action)

        menu.exec_(self.mapToGlobal(self.pos() + self.rect().topRight()))

    def add_new_tab(self, qurl=None, label="New Tab"):
        if qurl is None or not isinstance(qurl, QUrl):
            start_page_path = os.path.join(sys.path[0], 'start_page.html')
            qurl = QUrl.fromLocalFile(start_page_path)

        browser = QWebEngineView()
        browser.setUrl(qurl)

        i = self.tabs.addTab(browser, label)
        self.tabs.setCurrentIndex(i)

        browser.urlChanged.connect(lambda qurl, browser=browser: self.update_urlbar(qurl, browser))
        self.update_urlbar(qurl, browser)

    def close_current_tab(self, i):
        if self.tabs.count() > 1:
            self.tabs.removeTab(i)

    def navigate_home(self):
        start_page_path = os.path.join(sys.path[0], '..', 'start_page.html')
        start_page_url = QUrl.fromLocalFile(start_page_path)
        self.tabs.currentWidget().setUrl(start_page_url)

    def navigate_back(self):
        if self.tabs.currentWidget():
            self.tabs.currentWidget().back()

    def navigate_forward(self):
        if self.tabs.currentWidget():
            self.tabs.currentWidget().forward()

    def reload_page(self):
        if self.tabs.currentWidget():
            self.tabs.currentWidget().reload()

    def navigate_to_url(self):
        url = self.url_bar.text()
        if not url.startswith('http'):
            url = f'{self.search_engine}{url}'
        self.tabs.currentWidget().setUrl(QUrl(url))

    def update_urlbar(self, qurl, browser=None):
        if browser == self.tabs.currentWidget():
            self.url_bar.setText(qurl.toString())

    def current_tab_changed(self, i):
        qurl = self.tabs.currentWidget().url()
        self.update_urlbar(qurl, self.tabs.currentWidget())

    def tab_open_doubleclick(self, i):
        if i == -1:
            self.add_new_tab()

    def get_search_engine_url(self):
        search = self.config.get('Settings', 'search', fallback='google')
        if search == 'google':
            return 'https://www.google.com/search?q='
        elif search == 'duckduckgo':
            return 'https://duckduckgo.com/?q='
        return 'https://www.google.com/search?q='

if __name__ == '__main__':
    app = QApplication(sys.argv)
    browser = Browser()
    app.exec_()
