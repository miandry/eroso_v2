document.addEventListener('DOMContentLoaded', function() {
  
  
  // Sidebar toggle
  const menuButton = document.getElementById('menu-button');
  const closeMenu = document.getElementById('close-menu');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  menuButton.addEventListener('click', function() {
  sidebar.classList.remove('-translate-x-full');
  overlay.classList.add('opacity-50');
  overlay.classList.remove('pointer-events-none');
  });
  closeMenu.addEventListener('click', function() {
  sidebar.classList.add('-translate-x-full');
  overlay.classList.remove('opacity-50');
  overlay.classList.add('pointer-events-none');
  });
  overlay.addEventListener('click', function() {
  sidebar.classList.add('-translate-x-full');
  overlay.classList.remove('opacity-50');
  overlay.classList.add('pointer-events-none');
  });

  
});	