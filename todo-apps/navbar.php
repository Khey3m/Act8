<?php
// ...existing code...
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"><strong>TODO App</strong></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="categories.php">Categories</a></li>
                <li class="nav-item"><a class="nav-link" href="todos.php">TODOs</a></li>
            </ul>

            <!-- Theme toggle and logout -->
            <div class="d-flex align-items-center">
                <button id="themeToggle" class="btn btn-outline-light me-2" type="button" title="Toggle dark mode">🌙</button>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </div>
</nav>

<!-- Simple dark mode styles (applies when <html> has .dark) -->
<style>
/* color variables fallback */
:root{
  --bg:#ffffff;
  --text:#212529;
  --card:#ffffff;
  --muted:#6c757d;
}

/* dark theme overrides */
html.dark {
  --bg:#0f1720;
  --text:#e6eef6;
  --card:#12171b;
  --muted:#9aa6b2;
  background-color: var(--bg) !important;
  color: var(--text) !important;
}

/* apply to common elements */
html.dark body { background-color: var(--bg); color: var(--text); }
html.dark .navbar { background-color: #0b1220 !important; }
html.dark .card { background-color: var(--card); color: var(--text); border-color: rgba(255,255,255,0.05); }
html.dark .table { color: var(--text); }
html.dark .text-muted, html.dark .muted { color: var(--muted) !important; }
html.dark .btn-outline-light { color: var(--text); border-color: rgba(255,255,255,0.12); }
html.dark .modal-content { background-color: var(--card); color: var(--text); }
</style>

<script>
// Theme toggle script: store in localStorage and respect prefers-color-scheme
(function(){
  const key = 'todo_theme';
  const toggle = document.getElementById('themeToggle');
  if (!toggle) return;

  function setTheme(isDark){
    document.documentElement.classList.toggle('dark', isDark);
    toggle.textContent = isDark ? '☀️' : '🌙';
    localStorage.setItem(key, isDark ? 'dark' : 'light');
  }

  // initial: localStorage -> prefers-color-scheme -> default light
  const saved = localStorage.getItem(key);
  if (saved === 'dark') setTheme(true);
  else if (saved === 'light') setTheme(false);
  else {
    const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    setTheme(prefers);
  }

  toggle.addEventListener('click', () => {
    setTheme(!document.documentElement.classList.contains('dark'));
  });
})();
</script>
<?php
// ...existing code...
?>
