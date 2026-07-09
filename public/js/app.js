/**
 * VDSt Kassensystem - gemeinsames JavaScript
 *
 * Wird in layouts/main.php eingebunden und stellt die Funktionen bereit,
 * die vorher in mehreren Views dupliziert waren.
 */

// Bestätigungsdialog für Lösch-Aktionen
function confirmDelete(message) {
    return confirm(message || 'Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?');
}

// Banner-Meldung oberhalb des Inhalts anzeigen (type: 'success' | 'error' | 'info')
function showMessage(message, type) {
    const cssType = type === 'error' ? 'danger' : (type || 'info');
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + cssType + ' alert-dismissible fade show';
    alertDiv.textContent = message;

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'btn-close';
    closeButton.setAttribute('data-bs-dismiss', 'alert');
    alertDiv.appendChild(closeButton);

    const mainContent = document.querySelector('.main-content');
    mainContent.insertBefore(alertDiv, mainContent.firstChild);

    setTimeout(function () {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 4000);
}

// ===== Toasts für Export-Feedback =====

function showLoadingToast(title, message) {
    const toastHtml = '<div class="toast-container position-fixed top-0 end-0 p-3">'
        + '<div id="loadingToast" class="toast show" role="alert">'
        + '<div class="toast-header bg-info text-white">'
        + '<div class="spinner-border spinner-border-sm me-2" role="status"></div>'
        + '<strong class="me-auto">' + title + '</strong>'
        + '</div>'
        + '<div class="toast-body">' + message + '</div>'
        + '</div></div>';

    document.body.insertAdjacentHTML('beforeend', toastHtml);
}

function hideLoadingToast() {
    const loadingToast = document.getElementById('loadingToast');
    if (loadingToast) {
        loadingToast.remove();
    }
}

function showSuccessToast(title, message) {
    const toastHtml = '<div class="toast-container position-fixed top-0 end-0 p-3">'
        + '<div class="toast show" role="alert" data-bs-autohide="true" data-bs-delay="3000">'
        + '<div class="toast-header bg-success text-white">'
        + '<strong class="me-auto">' + title + '</strong>'
        + '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>'
        + '</div>'
        + '<div class="toast-body">' + message + '</div>'
        + '</div></div>';

    document.body.insertAdjacentHTML('beforeend', toastHtml);
}

// Hängt Export-Feedback an alle ZIP-/Excel-Export-Links
function initializeExportFeedback() {
    document.querySelectorAll('a[href*="/downloadZip/"], a[href*="/export/zip"]').forEach(function (link) {
        link.addEventListener('click', function () {
            showLoadingToast('ZIP-Archiv wird erstellt...', 'Das kann einen Moment dauern.');

            setTimeout(function () {
                hideLoadingToast();
                showSuccessToast('ZIP-Download gestartet!', 'Das Archiv wurde erstellt und der Download gestartet.');
            }, 2000);
        });
    });

    document.querySelectorAll('a[href*="/exportExcel"], a[href*="/export/excel"]').forEach(function (link) {
        link.addEventListener('click', function () {
            showSuccessToast('Excel-Export gestartet!', 'Die Datei wird heruntergeladen.');
        });
    });
}

// Formatiert eine Betrag-Eingabe beim Verlassen auf 2 Dezimalstellen
function bindBetragFormat(input) {
    input.addEventListener('blur', function () {
        const value = parseFloat(this.value);
        if (!isNaN(value)) {
            this.value = value.toFixed(2);
        }
    });
}

// Sendet das umgebende Formular automatisch ab, sobald sich das Select ändert
function bindAutoSubmit(select) {
    select.addEventListener('change', function () {
        this.form.submit();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // Flash-Messages nach 5 Sekunden automatisch ausblenden
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(function (alert) {
        setTimeout(function () {
            if (alert && alert.parentNode && window.bootstrap) {
                new bootstrap.Alert(alert).close();
            }
        }, 5000);
    });

    // Gemeinsame Verhaltensweisen per Klasse binden (vorher pro View dupliziert)
    document.querySelectorAll('input.js-betrag-format').forEach(bindBetragFormat);
    document.querySelectorAll('select.js-autosubmit').forEach(bindAutoSubmit);

    initializeExportFeedback();
});
