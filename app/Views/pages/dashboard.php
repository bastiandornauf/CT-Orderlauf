<section class="page-section" x-data="dashboardPage()" x-init="init()">
    <h1 class="page-title">Start</h1>
    <p class="page-lead">Mobile Bestellhilfe – offline im Lager, Ausgabe mit Netz.</p>

    <div class="button-stack">
        <a href="/order/prepare" class="button button--primary button--block">Bestellung starten</a>
        <a href="/order/round" class="button button--secondary button--block" x-show="hasRound && (roundStatus === 'prepared' || roundStatus === 'active' || roundStatus === 'paused')">Rundgang fortsetzen</a>
        <a href="/order/review" class="button button--secondary button--block" x-show="hasRound && (roundStatus === 'ready_for_review' || roundStatus === 'active' || roundStatus === 'paused')">Kontrolle / Abschluss</a>
        <a href="/order/output" class="button button--secondary button--block" x-show="hasRound && roundStatus === 'ready_for_review'">Ausgabe</a>
    </div>

    <h2 class="section-header">Verwaltung</h2>
    <ul class="link-list card card--pad">
        <li><a href="/locations">Lagerorte</a></li>
        <li><a href="/suppliers">Lieferanten</a></li>
        <li><a href="/items">Artikel</a></li>
        <li><a href="/import">CSV-Import</a></li>
        <li><a href="/settings">Einstellungen</a></li>
    </ul>
</section>
