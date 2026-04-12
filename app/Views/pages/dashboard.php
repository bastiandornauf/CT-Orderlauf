<section class="page-section" x-data="dashboardPage()" x-init="init()">
    <h1 class="page-title">Start</h1>
    <p class="page-lead">Bestellrunden durch den Lager-Rundgang, Kontrolle und Ausgabe per E-Mail oder PDF.</p>

    <div class="dashboard-quick card card--pad">
        <p class="section-header" style="margin-top:0">Stammdaten</p>
        <ul class="dashboard-quick__list">
            <li><a href="/items" class="dashboard-quick__link">Artikel <span class="dashboard-quick__count"><?= (int) ($counts['items'] ?? 0) ?></span></a></li>
            <li><a href="/suppliers" class="dashboard-quick__link">Lieferanten <span class="dashboard-quick__count"><?= (int) ($counts['suppliers'] ?? 0) ?></span></a></li>
            <li><a href="/locations" class="dashboard-quick__link">Lagerorte <span class="dashboard-quick__count"><?= (int) ($counts['locations'] ?? 0) ?></span></a></li>
        </ul>
    </div>

    <template x-if="hasRound && roundStatusLabel">
        <div class="card card--pad dashboard-round-status">
            <p class="section-header" style="margin-top:0">Aktuelle Runde</p>
            <p class="text-muted" style="margin:0" x-text="roundStatusLabel"></p>
        </div>
    </template>

    <div class="button-stack">
        <a href="/order/prepare" class="button button--block"
 :class="hasRound && (roundStatus === 'prepared' || roundStatus === 'active' || roundStatus === 'paused') ? 'button--secondary' : 'button--primary'">Neue Bestellung starten</a>
        <a href="/order/round" class="button button--primary button--block" x-show="hasRound && (roundStatus === 'prepared' || roundStatus === 'active' || roundStatus === 'paused')">Rundgang fortsetzen</a>
        <a href="/order/review" class="button button--secondary button--block" x-show="hasRound && (roundStatus === 'ready_for_review' || roundStatus === 'active' || roundStatus === 'paused')">Kontrolle / Abschluss</a>
        <a href="/order/output" class="button button--secondary button--block" x-show="hasRound && roundStatus === 'ready_for_review'">Ausgabe</a>
    </div>

    <p class="text-muted dashboard-hint">Weitere Bereiche: <strong>Menü</strong> oben (drei Striche auf dem Handy).</p>
</section>
