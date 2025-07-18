<!DOCTYPE html>
<html>
<body>
<script>
  // Этот скрипт блокирует контекстное меню
  document.addEventListener('contextmenu', function(event) {
    event.preventDefault(); // Это главное: отменяем стандартное действие
  });
</script>

</body>
</html>