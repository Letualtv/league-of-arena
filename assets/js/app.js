/* ===== Toast ===== */
function showToast(msg, duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), duration);
}

/* ===== Actualizar perfil (icono/nivel) ===== */
const btnSync = document.getElementById('btn-sync');
if (btnSync) {
    btnSync.addEventListener('click', async () => {
        const { puuid, region } = btnSync.dataset;
        const msgEl = document.getElementById('sync-msg');

        btnSync.disabled = true;
        btnSync.textContent = 'Actualizando...';

        try {
            const form = new FormData();
            form.append('puuid', puuid);
            form.append('region', region);

            const res  = await fetch(BASE_URL + 'ajax/sync_matches.php', { method: 'POST', body: form });
            const data = await res.json();

            msgEl.style.display = 'block';
            msgEl.className = 'alert ' + (data.ok ? 'alert-success' : 'alert-error');
            msgEl.textContent = data.mensaje || data.error;

            if (data.ok) setTimeout(() => location.reload(), 1000);
        } catch {
            msgEl.style.display = 'block';
            msgEl.className = 'alert alert-error';
            msgEl.textContent = 'Error de conexion.';
        } finally {
            btnSync.disabled = false;
            btnSync.innerHTML = '&#8635; Actualizar perfil';
        }
    });
}

/* ===== Toggle campeon ganado ===== */
document.querySelectorAll('.btn-toggle-ganado').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const { puuid, region, id: championId, nombre: championName, clase: championClase } = btn.dataset;

        const form = new FormData();
        form.append('puuid', puuid);
        form.append('region', region);
        form.append('champion_id', championId);
        form.append('champion_name', championName);
        form.append('champion_clase', championClase);

        btn.disabled = true;
        try {
            const res  = await fetch(BASE_URL + 'ajax/toggle_campeon.php', { method: 'POST', body: form });
            const data = await res.json();

            if (!data.ok) {
                showToast('⚠ ' + data.error);
                return;
            }

            const card  = btn.closest('.champ-card');
            const badge = card.querySelector('.champ-card-badge');

            if (data.ganado) {
                card.classList.add('ganado');
                card.dataset.ganado = '1';
                btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                btn.title = 'Quitar marca';
                card.classList.remove('just-marked');
                void card.offsetWidth; // reflow para reiniciar animación
                card.classList.add('just-marked');
                setTimeout(() => card.classList.remove('just-marked'), 500);
                if (!badge) {
                    const b = document.createElement('div');
                    b.className = 'champ-card-badge';
                    b.innerHTML = '&#10003;';
                    card.querySelector('.champ-card-img-wrap').appendChild(b);
                }
                actualizarContador(1);
            } else {
                card.classList.remove('ganado');
                card.dataset.ganado = '0';
                btn.innerHTML = '<i class="fa-solid fa-plus"></i>';
                btn.title = 'Marcar como ganado';
                if (badge) badge.remove();
                actualizarContador(-1);
            }

            if (data.nuevos_logros && data.nuevos_logros.length > 0) {
                data.nuevos_logros.forEach(n => showToast('Logro desbloqueado: ' + n));
            }
        } catch {
            showToast('Error de conexion.');
        } finally {
            btn.disabled = false;
        }
    });
});

function actualizarContador(delta) {
    // Actualizar texto del subtítulo
    const goldEl = document.querySelector('.page-subtitle .text-gold');
    if (goldEl) goldEl.textContent = parseInt(goldEl.textContent) + delta;

    // Actualizar barra de progreso
    const label = document.querySelector('.progress-label span:last-child');
    const fill  = document.querySelector('.progress-fill');
    if (label && fill) {
        const parts = label.textContent.split('/');
        if (parts.length === 2) {
            const actual = parseInt(parts[0]) + delta;
            const total  = parseInt(parts[1]);
            label.textContent = actual + ' / ' + total;
            fill.style.width = Math.round((actual / total) * 100) + '%';
        }
    }
}

/* ===== Filtros de clase ===== */
document.querySelectorAll('.clase-filtro').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.clase-filtro').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        claseActiva = btn.dataset.clase;
        filtrarCampeones();
    });
});

/* ===== Búsqueda y filtro ===== */
let claseActiva = '';
const searchInput = document.getElementById('champ-search');
const onlyWon     = document.getElementById('only-won');

function filtrarCampeones() {
    const query       = (searchInput?.value || '').toLowerCase().trim();
    const soloGanados = onlyWon?.checked || false;

    document.querySelectorAll('.champ-card').forEach(card => {
        const nombre  = card.dataset.nombre || '';
        const ganado  = card.dataset.ganado === '1';
        const clase   = card.dataset.clase  || '';
        const visible =
            nombre.includes(query) &&
            (!soloGanados || ganado) &&
            (!claseActiva || clase === claseActiva);

        card.classList.toggle('hidden', !visible);
    });
}

searchInput?.addEventListener('input', filtrarCampeones);
onlyWon?.addEventListener('change', filtrarCampeones);
