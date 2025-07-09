<?php
// Этот PHP просто отдаёт саму страницу, url для iframe будем менять через JS
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8" />
<title>Браузер в браузере</title>
<style>
  body { margin: 0; font-family: Arial, sans-serif; }
  #tabs { display: flex; background: #222; padding: 5px; }
  #tabs button {
    background: #444; border: none; color: white; padding: 8px 12px; margin-right: 4px; cursor: pointer;
  }
  #tabs button.active {
    background: #0078d7;
  }
  #nav { margin: 5px 0; }
  #nav button {
    padding: 6px 10px; margin-right: 5px; cursor: pointer;
  }
  #urlInput {
    width: 60%; padding: 6px;
    font-size: 16px;
  }
  iframe {
    width: 100%; height: 80vh; border: 1px solid #444;
  }
</style>
</head>
<body>
<div id="tabs"></div>

<div id="nav">
  <button id="backBtn" disabled>← Назад</button>
  <button id="forwardBtn" disabled>→ Вперёд</button>
  <button id="reloadBtn">↻ Обновить</button>
  <button id="newTabBtn">+ Новая вкладка</button>
  <button id="closeTabBtn">× Закрыть вкладку</button>
  <input type="text" id="urlInput" placeholder="https://..." />
  <button id="goBtn">Открыть</button>
</div>

<iframe id="browserFrame" src="https://google.com"></iframe>

<script>
  // Массив вкладок: {url: string, history: [urls], historyIndex: int}
  let tabs = [];
  let currentTab = 0;

  const tabsDiv = document.getElementById('tabs');
  const iframe = document.getElementById('browserFrame');
  const urlInput = document.getElementById('urlInput');
  const backBtn = document.getElementById('backBtn');
  const forwardBtn = document.getElementById('forwardBtn');
  const reloadBtn = document.getElementById('reloadBtn');
  const newTabBtn = document.getElementById('newTabBtn');
  const closeTabBtn = document.getElementById('closeTabBtn');
  const goBtn = document.getElementById('goBtn');

  function renderTabs() {
    tabsDiv.innerHTML = '';
    tabs.forEach((tab, i) => {
      const btn = document.createElement('button');
      btn.textContent = tab.url.length > 30 ? tab.url.slice(0,27)+'...' : tab.url;
      btn.className = i === currentTab ? 'active' : '';
      btn.onclick = () => switchTab(i);
      tabsDiv.appendChild(btn);
    });
  }

  function updateNavButtons() {
    const tab = tabs[currentTab];
    backBtn.disabled = tab.historyIndex <= 0;
    forwardBtn.disabled = tab.historyIndex >= tab.history.length - 1;
    urlInput.value = tab.url;
  }

  function switchTab(i) {
    currentTab = i;
    iframe.src = tabs[i].url;
    updateNavButtons();
    renderTabs();
  }

  function navigateTo(url) {
    if (!url.startsWith('http')) url = 'https://' + url;
    let tab = tabs[currentTab];
    // Обрезаем историю после текущей позиции
    tab.history = tab.history.slice(0, tab.historyIndex + 1);
    tab.history.push(url);
    tab.historyIndex++;
    tab.url = url;
    iframe.src = url;
    updateNavButtons();
    renderTabs();
  }

  backBtn.onclick = () => {
    let tab = tabs[currentTab];
    if (tab.historyIndex > 0) {
      tab.historyIndex--;
      tab.url = tab.history[tab.historyIndex];
      iframe.src = tab.url;
      updateNavButtons();
      renderTabs();
    }
  };

  forwardBtn.onclick = () => {
    let tab = tabs[currentTab];
    if (tab.historyIndex < tab.history.length - 1) {
      tab.historyIndex++;
      tab.url = tab.history[tab.historyIndex];
      iframe.src = tab.url;
      updateNavButtons();
      renderTabs();
    }
  };

  reloadBtn.onclick = () => {
    iframe.src = tabs[currentTab].url;
  };

  newTabBtn.onclick = () => {
    tabs.push({
      url: 'https://google.com',
      history: ['https://google.com'],
      historyIndex: 0
    });
    currentTab = tabs.length - 1;
    switchTab(currentTab);
  };

  closeTabBtn.onclick = () => {
    if (tabs.length === 1) {
      alert('Нельзя закрыть последнюю вкладку!');
      return;
    }
    tabs.splice(currentTab, 1);
    if (currentTab >= tabs.length) currentTab = tabs.length - 1;
    switchTab(currentTab);
  };

  goBtn.onclick = () => {
    navigateTo(urlInput.value.trim());
  };

  urlInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      navigateTo(urlInput.value.trim());
    }
  });

  // Инициализация — стартовая вкладка
  tabs = [{
    url: 'https://google.com',
    history: ['https://google.com'],
    historyIndex: 0
  }];

  switchTab(0);
</script>

</body>
</html>
