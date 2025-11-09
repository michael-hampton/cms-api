<div style="display: flex; align-items: center; gap: 1rem; justify-content: space-between;">
    <h1 class="page-title"><?= htmlspecialchars($page->title) ?></h1>
    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard" class="btn btn-primary">
        <!-- Person / Account icon (outline) -->
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             width="24" height="24"
             role="img"
             aria-labelledby="personTitle personDesc"
             focusable="false"
             fill="none"
             stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <title id="personTitle">Account</title>
            <desc id="personDesc">Link to account dashboard</desc>

            <!-- head -->
            <circle cx="12" cy="8" r="3.2"/>
            <!-- shoulders / torso -->
            <path d="M4.5 20c0-3.2 2.8-5.8 7.5-5.8s7.5 2.6 7.5 5.8"/>
        </svg>
    </a>
</div>