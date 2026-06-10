/**
 * SIGDOC-ML — JavaScript principal
 */

// ── Confirmaciones de eliminación ──
document.addEventListener('DOMContentLoaded', () => {

    // Confirmar eliminación
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            const msg = el.dataset.confirm || '¿Está seguro de realizar esta acción?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // Auto-cerrar alertas después de 4 s
    document.querySelectorAll('.alert-auto').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
    });

    // Preview de archivo seleccionado
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', () => {
            const target = document.getElementById(input.dataset.preview);
            if (target && input.files.length) {
                target.textContent = input.files[0].name;
            }
        });
    });
});

// ── Búsqueda en tablas locales ──
function filtrarTabla(inputId, tablaId) {
    const filtro = document.getElementById(inputId).value.toLowerCase();
    const filas  = document.querySelectorAll(`#${tablaId} tbody tr`);
    filas.forEach(fila => {
        fila.style.display = fila.textContent.toLowerCase().includes(filtro) ? '' : 'none';
    });
}

// ── Formatear scores ML con color ──
function colorScore(pct) {
    if (pct >= 70) return 'score-high';
    if (pct >= 40) return 'score-medium';
    return 'score-low';
}

// ── Imprimir sección ──
function imprimirSeccion(id) {
    const content = document.getElementById(id).innerHTML;
    const win = window.open('', '_blank');
    win.document.write(`<html><head><title>Imprimir</title>
        <link rel="stylesheet" href="${window.location.origin}/SIGDOC-ML/assets/css/style.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        </head><body class="p-4">${content}</body></html>`);
    win.document.close();
    win.print();
}
