<?php
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
$activeNav = 'apis';
$user = currentUser();
$db = getDB();

$savedSt = $db->prepare("
  SELECT *
  FROM external_resources
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 8
");
$savedSt->execute([(int)$user['id']]);
$savedResources = $savedSt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>External APIs - TaskFlow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main class="page-wrapper">
  <header class="page-header">
    <div>
      <h1 class="page-title">External APIs</h1>
      <p class="page-subtitle">Third-party data examples connected to TaskFlow</p>
    </div>
  </header>

  <section class="api-lab-grid">
    <article class="card-tf card-tf-body">
      <div class="api-card-head">
        <span class="badge-tf badge-low">Static HTML API</span>
        <a href="https://httpbin.org/html" target="_blank" rel="noopener" class="small">Source</a>
      </div>
      <h2 class="api-card-title">Fixed Third-Party HTML</h2>
      <p class="text-muted small mb-3">Loaded from httpbin without sending user input.</p>
      <iframe id="staticHtmlFrame" class="api-static-frame" title="Static HTML API response" sandbox=""></iframe>
    </article>

    <article class="card-tf card-tf-body">
      <div class="api-card-head">
        <span class="badge-tf badge-progress">Dynamic JS API</span>
        <span class="small text-muted">Wikipedia OpenSearch</span>
      </div>
      <h2 class="api-card-title">Search Wikipedia</h2>
      <form id="wikiSearchForm" class="d-flex gap-2 mb-3">
        <input type="search" id="wikiSearchInput" class="form-control" placeholder="Try: project management" required>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
      </form>
      <div id="wikiSearchResults" class="api-result-list">
        <div class="text-muted small">Search results will appear here.</div>
      </div>
    </article>

    <article class="card-tf card-tf-body api-db-card">
      <div class="api-card-head">
        <span class="badge-tf badge-done">API + Database</span>
        <span class="small text-muted">Saved to taskflow_db</span>
      </div>
      <h2 class="api-card-title">Save External Research</h2>
      <form id="saveResourceForm" class="d-flex gap-2 mb-3">
        <input type="hidden" id="apiCsrfToken" value="<?= csrf() ?>">
        <input type="search" id="saveResourceInput" class="form-control" placeholder="Try: Kanban" required>
        <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-plus"></i></button>
      </form>
      <div id="saveResourceStatus" class="small text-muted mb-3"></div>

      <div id="savedResources" class="api-result-list">
        <?php if (empty($savedResources)): ?>
          <div class="text-muted small">No saved external resources yet.</div>
        <?php else: ?>
          <?php foreach ($savedResources as $resource): ?>
            <article class="api-result-item">
              <div class="d-flex justify-content-between gap-2 align-items-start">
                <h3><?= e($resource['title']) ?></h3>
                <span class="badge-tf badge-low"><?= e($resource['api_source']) ?></span>
              </div>
              <?php if ($resource['summary_text']): ?>
                <p><?= e($resource['summary_text']) ?></p>
              <?php endif; ?>
              <a href="<?= e($resource['source_url']) ?>" target="_blank" rel="noopener">Open source</a>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </article>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const staticFrame = document.getElementById('staticHtmlFrame');
  fetch('https://httpbin.org/html')
    .then(res => res.text())
    .then(html => { staticFrame.srcdoc = html; })
    .catch(() => { staticFrame.srcdoc = '<p>Static API content could not be loaded.</p>'; });

  const wikiForm = document.getElementById('wikiSearchForm');
  const wikiInput = document.getElementById('wikiSearchInput');
  const wikiResults = document.getElementById('wikiSearchResults');

  wikiForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const query = wikiInput.value.trim();
    if (!query) return;
    wikiResults.innerHTML = '<div class="text-muted small">Searching...</div>';

    try {
      const url = `https://en.wikipedia.org/w/api.php?action=opensearch&limit=5&namespace=0&format=json&origin=*&search=${encodeURIComponent(query)}`;
      const data = await fetch(url).then(res => res.json());
      const titles = data[1] || [];
      const descriptions = data[2] || [];
      const links = data[3] || [];

      wikiResults.innerHTML = titles.length ? '' : '<div class="text-muted small">No results found.</div>';
      titles.forEach((title, index) => {
        const item = document.createElement('article');
        item.className = 'api-result-item';
        item.innerHTML = `
          <h3></h3>
          <p></p>
          <a target="_blank" rel="noopener">Open source</a>
        `;
        item.querySelector('h3').textContent = title;
        item.querySelector('p').textContent = descriptions[index] || 'Wikipedia article';
        item.querySelector('a').href = links[index];
        wikiResults.appendChild(item);
      });
    } catch {
      wikiResults.innerHTML = '<div class="text-danger small">Wikipedia search failed.</div>';
    }
  });

  const saveForm = document.getElementById('saveResourceForm');
  const saveInput = document.getElementById('saveResourceInput');
  const saveStatus = document.getElementById('saveResourceStatus');
  const savedResources = document.getElementById('savedResources');

  saveForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const topic = saveInput.value.trim();
    if (!topic) return;
    saveStatus.textContent = 'Fetching and saving...';

    try {
      const response = await fetch('../api/external_resources.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ topic, csrf: document.getElementById('apiCsrfToken').value })
      });
      const data = await response.json();
      if (!data.success) throw new Error(data.error || 'Could not save resource.');

      const item = document.createElement('article');
      item.className = 'api-result-item';
      item.innerHTML = `
        <div class="d-flex justify-content-between gap-2 align-items-start">
          <h3></h3>
          <span class="badge-tf badge-low">Wikipedia</span>
        </div>
        <p></p>
        <a target="_blank" rel="noopener">Open source</a>
      `;
      item.querySelector('h3').textContent = data.resource.title;
      item.querySelector('p').textContent = data.resource.summary_text || 'Saved external resource.';
      item.querySelector('a').href = data.resource.source_url;

      if (savedResources.querySelector('.text-muted')) savedResources.innerHTML = '';
      savedResources.prepend(item);
      saveInput.value = '';
      saveStatus.textContent = 'Saved to database.';
    } catch (error) {
      saveStatus.textContent = error.message || 'Could not save resource.';
    }
  });
});
</script>
</body>
</html>
