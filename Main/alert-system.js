/**
 * Custom Alert System - Replace browser alerts with styled notifications
 * Supports: success, error, warning, info
 */

class AlertSystem {
    constructor() {
        this.container = null;
        this.initContainer();
    }

    initContainer() {
        // Create container if it doesn't exist
        if (!document.getElementById('alert-container')) {
            const container = document.createElement('div');
            container.id = 'alert-container';
            document.body.appendChild(container);
            this.container = container;
        } else {
            this.container = document.getElementById('alert-container');
        }
    }

    show(message, type = 'info', duration = 4000) {
        if (!this.container) {
            this.initContainer();
        }

        const alertId = 'alert-' + Date.now() + Math.random();
        const alert = document.createElement('div');
        alert.id = alertId;
        alert.className = `alert alert-${type} animate-in`;

        // Icon mapping
        const icons = {
            success: '<i class="fa-solid fa-circle-check"></i>',
            error: '<i class="fa-solid fa-circle-exclamation"></i>',
            warning: '<i class="fa-solid fa-triangle-exclamation"></i>',
            info: '<i class="fa-solid fa-circle-info"></i>'
        };

        alert.innerHTML = `
            <div class="alert-icon">${icons[type] || icons.info}</div>
            <div class="alert-content">
                <p class="alert-message">${this.escapeHtml(message)}</p>
            </div>
            <button class="alert-close" data-alert-id="${alertId}"><i class="fa-solid fa-xmark"></i></button>
            <div class="alert-progress">
                <div class="alert-progress-bar" style="transition-duration: ${duration}ms;"></div>
            </div>
        `;

        this.container.appendChild(alert);

        // Close button handler
        alert.querySelector('.alert-close').addEventListener('click', () => {
            this.removeAlert(alertId);
        });

        // Auto-close and progress bar logic
        if (duration > 0) {
            // Trigger reflow for animation
            setTimeout(() => {
                const bar = alert.querySelector('.alert-progress-bar');
                if (bar) bar.style.width = '0%';
            }, 10);

            setTimeout(() => {
                this.removeAlert(alertId);
            }, duration);
        } else {
            const progress = alert.querySelector('.alert-progress');
            if (progress) progress.remove();
        }

        return alertId;
    }

    removeAlert(alertId) {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.classList.add('animate-out');
            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    }

    success(message, duration = 3000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 5000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 4000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 3000) {
        return this.show(message, 'info', duration);
    }

    confirm(message, onConfirm, onCancel) {
        if (!this.container) {
            this.initContainer();
        }

        const alertId = 'confirm-' + Date.now() + Math.random();
        const modal = document.createElement('div');
        modal.id = alertId;
        modal.className = 'alert-modal animate-in';

        modal.innerHTML = `
            <div class="alert-backdrop"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-header-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <h3>Confirm Action</h3>
                </div>
                <div class="modal-body">
                    <p>${this.escapeHtml(message)}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-cancel" data-action="cancel">Cancel</button>
                    <button class="btn btn-confirm" data-action="confirm">Confirm</button>
                </div>
            </div>
        `;

        this.container.appendChild(modal);

        const handleClose = (action) => {
            modal.classList.add('animate-out');
            setTimeout(() => {
                modal.remove();
                if (action === 'confirm' && onConfirm) onConfirm();
                if (action === 'cancel' && onCancel) onCancel();
            }, 300);
        };

        modal.querySelector('[data-action="confirm"]').addEventListener('click', () => {
            handleClose('confirm');
        });

        modal.querySelector('[data-action="cancel"]').addEventListener('click', () => {
            handleClose('cancel');
        });

        modal.querySelector('.alert-backdrop').addEventListener('click', () => {
            handleClose('cancel');
        });

        return alertId;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize globally
const Alert = new AlertSystem();
