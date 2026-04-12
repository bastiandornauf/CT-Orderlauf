<section class="page-section" x-data="preparePage()">
    <h1 class="page-title">Bestellung vorbereiten</h1>
    <p class="text-muted">Wählen Sie das Ziel-Datum (Lieferung). Es werden alle Stammdaten für den Offline-Rundgang geladen.</p>

    <div class="card card--pad form-stack">
        <div class="form-group">
            <label class="form-label" for="target_date">Ziel-Datum</label>
            <input class="input" type="date" id="target_date" x-model="targetDate">
        </div>
        <button type="button" class="button button--primary" @click="loadRound()" :disabled="loading">
            <span x-show="!loading">Bestellrunde laden</span>
            <span x-show="loading">Lade…</span>
        </button>
        <p class="toast toast--error" x-show="error" x-text="error"></p>
    </div>

    <template x-if="delivering.length">
        <div class="card card--pad">
            <h2 class="section-header">Lieferanten am Zieltag</h2>
            <ul class="bullet-list">
                <template x-for="s in delivering" :key="s.id">
                    <li x-text="s.name"></li>
                </template>
            </ul>
        </div>
    </template>

    <template x-if="notDelivering.length">
        <div class="card card--pad">
            <h2 class="section-header">Lieferanten nicht am Zieltag</h2>
            <ul class="bullet-list text-muted">
                <template x-for="s in notDelivering" :key="s.id">
                    <li x-text="s.name"></li>
                </template>
            </ul>
        </div>
    </template>
</section>
