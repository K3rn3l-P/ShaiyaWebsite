

// Simple interaction for buttons (shimmer effect)
document.querySelectorAll('.btn-action').forEach(btn => {
    btn.addEventListener('mouseenter', function() {
        this.style.boxShadow = '0 0 25px rgba(198, 162, 96, 0.5), inset 0 0 15px rgba(198, 162, 96, 0.4)';
    });
    
    btn.addEventListener('mouseleave', function() {
        this.style.boxShadow = '0 0 15px rgba(0,0,0,0.8), inset 0 0 10px rgba(198, 162, 96, 0.2)';
    });
});

document.addEventListener('change', function (event) {
    const autoSubmitInput = event.target.closest('.js-auto-submit-onchange');
    if (autoSubmitInput && autoSubmitInput.form) {
        autoSubmitInput.form.submit();
        return;
    }

    const checkbox = event.target.closest('.custom-checkbox');
    if (!checkbox || !checkbox.checked) {
        return;
    }

    document.querySelectorAll('.custom-checkbox').forEach(function (other) {
        if (other !== checkbox) {
            other.checked = false;
        }
    });
});

document.addEventListener('click', function (event) {
    const schedulerModeButton = event.target.closest('.js-scheduler-mode[data-mode]');
    if (schedulerModeButton) {
        const mode = schedulerModeButton.getAttribute('data-mode') === 'advanced' ? 'advanced' : 'simple';
        const simpleForm = document.getElementById('scheduler-simple-form');
        const advancedForm = document.getElementById('scheduler-advanced-form');
        const simpleButton = document.getElementById('scheduler-mode-simple-btn');
        const advancedButton = document.getElementById('scheduler-mode-advanced-btn');

        if (simpleForm && advancedForm) {
            const isSimple = mode === 'simple';
            simpleForm.style.display = isSimple ? 'block' : 'none';
            advancedForm.style.display = isSimple ? 'none' : 'block';
        }

        if (simpleButton && advancedButton) {
            const isSimple = mode === 'simple';
            simpleButton.style.opacity = isSimple ? '1' : '0.75';
            advancedButton.style.opacity = isSimple ? '0.75' : '1';
        }
        return;
    }

    const resetActionTypes = event.target.closest('.js-reset-log-action-types');
    if (resetActionTypes) {
        document.querySelectorAll('#logsSearchForm select option').forEach(function (optionEl) {
            optionEl.selected = false;
        });
        return;
    }

    const setInputValueRow = event.target.closest('.js-set-input-value[data-target-id][data-value]');
    if (setInputValueRow) {
        const targetId = setInputValueRow.getAttribute('data-target-id');
        const value = setInputValueRow.getAttribute('data-value') || '';
        if (targetId) {
            const targetInput = document.getElementById(targetId);
            if (targetInput) {
                targetInput.value = value;
            }
        }
        return;
    }

    const selectItemRow = event.target.closest('.js-select-item-result[data-item-id][data-item-label]');
    if (selectItemRow) {
        const selectedItemId = selectItemRow.getAttribute('data-item-id') || '';
        const selectedItemLabel = selectItemRow.getAttribute('data-item-label') || '';
        const selectedItemInput = document.getElementById('selectedItemId');
        const selectedItemDisplay = document.getElementById('selectedItemDisplay');

        if (selectedItemInput) {
            selectedItemInput.value = selectedItemId;
        }

        if (selectedItemDisplay) {
            const parts = selectedItemLabel.split(' - ');
            const name = parts.length > 1 ? parts.slice(1).join(' - ') : selectedItemLabel;
            selectedItemDisplay.innerText = name;
        }

        if (selectedItemInput) {
            const card = selectedItemInput.parentElement && selectedItemInput.parentElement.parentElement;
            if (card) {
                card.style.borderColor = '#e8c881';
                setTimeout(function () {
                    card.style.borderColor = '#222';
                }, 500);
            }
        }
        return;
    }

    const editButton = event.target.closest('.js-news-edit[data-news-id]');
    if (editButton) {
        const newsId = parseInt(editButton.getAttribute('data-news-id') || '0', 10);
        if (newsId > 0 && typeof window.editNews === 'function') {
            window.editNews(newsId);
        }
        return;
    }

    const cancelButton = event.target.closest('.js-news-cancel-edit');
    if (cancelButton) {
        if (typeof window.cancelEdit === 'function') {
            window.cancelEdit();
        }
        return;
    }

    const overlay = event.target.closest('#editNewsModal.custom-modal-overlay');
    if (overlay && event.target === overlay && typeof window.cancelEdit === 'function') {
        window.cancelEdit();
    }
});

document.addEventListener('mouseover', function (event) {
    const hoverBorderElement = event.target.closest('.js-hover-border[data-border-hover]');
    if (!hoverBorderElement) {
        return;
    }
    hoverBorderElement.style.borderColor = hoverBorderElement.getAttribute('data-border-hover') || '';
});

document.addEventListener('mouseout', function (event) {
    const hoverBorderElement = event.target.closest('.js-hover-border[data-border-default]');
    if (!hoverBorderElement) {
        return;
    }
    const relatedTarget = event.relatedTarget;
    if (relatedTarget && hoverBorderElement.contains(relatedTarget)) {
        return;
    }
    hoverBorderElement.style.borderColor = hoverBorderElement.getAttribute('data-border-default') || '';
});

document.addEventListener('error', function (event) {
    const target = event.target;
    if (!(target instanceof HTMLImageElement)) {
        return;
    }

    if (!target.classList.contains('js-hide-onerror')) {
        return;
    }

    target.style.display = 'none';
}, true);

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
        return;
    }

    const editModal = document.getElementById('editNewsModal');
    if (!editModal || editModal.style.display !== 'flex') {
        return;
    }

    if (typeof window.cancelEdit === 'function') {
        window.cancelEdit();
    }
});

document.addEventListener('submit', function (event) {
    const confirmForm = event.target.closest('.js-confirm-submit');
    if (!confirmForm) {
        const formEl = event.target;
        if (formEl && formEl.id === 'news-form') {
            const hiddenContentInput = document.getElementById('hidden-content');
            const editorElement = document.querySelector('#editor-container .ql-editor');
            if (hiddenContentInput && editorElement) {
                hiddenContentInput.value = editorElement.innerHTML;
            }
            return;
        }

        if (formEl && formEl.id === 'modal-edit-form') {
            const hiddenModalContentInput = document.getElementById('modal-hidden-content');
            const modalEditorElement = document.querySelector('#modal-editor-container .ql-editor');
            if (hiddenModalContentInput && modalEditorElement) {
                hiddenModalContentInput.value = modalEditorElement.innerHTML;
            }
        }
        return;
    }

    const message = confirmForm.getAttribute('data-confirm') || 'Are you sure?';
    if (!confirm(message)) {
        event.preventDefault();
    }
});

(function () {
    const tabButtons = document.querySelectorAll('.tab-btn[data-tab-target]');
    if (tabButtons.length) {
        const tabContents = document.querySelectorAll('.tab-content');
        function setActiveTab(targetId, sourceButton) {
            tabContents.forEach(function (contentEl) {
                contentEl.classList.remove('active');
            });
            tabButtons.forEach(function (buttonEl) {
                buttonEl.classList.remove('active');
            });

            const targetPanel = document.getElementById(targetId);
            if (!targetPanel) {
                return;
            }

            targetPanel.classList.add('active');
            sourceButton.classList.add('active');
        }

        tabButtons.forEach(function (buttonEl) {
            buttonEl.addEventListener('click', function () {
                const targetId = buttonEl.getAttribute('data-tab-target');
                if (targetId) {
                    setActiveTab(targetId, buttonEl);
                }
            });
        });
    }

    const methodCards = document.querySelectorAll('.method-card[data-method]');
    if (!methodCards.length) {
        return;
    }

    const manualBanner = document.getElementById('donation-manual-banner');
    const actionButton = document.getElementById('donation-action-btn');

    function setMethod(method) {
        methodCards.forEach(function (card) {
            const isActive = card.getAttribute('data-method') === method;
            card.classList.toggle('active', isActive);
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        if (manualBanner) {
            manualBanner.style.display = method === 'revolut' ? 'block' : 'none';
        }

        if (actionButton) {
            if (method === 'revolut') {
                actionButton.innerHTML = '<i class="fas fa-comments"></i> Open Discord Ticket';
            } else {
                actionButton.innerHTML = '<i class="fab fa-paypal"></i> Donate!';
            }
        }
    }

    methodCards.forEach(function (card) {
        card.addEventListener('click', function () {
            setMethod(card.getAttribute('data-method') || 'paypal');
        });

        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                setMethod(card.getAttribute('data-method') || 'paypal');
            }
        });
    });

    const initialActiveCard = document.querySelector('.method-card[data-method].active');
    setMethod((initialActiveCard && initialActiveCard.getAttribute('data-method')) || 'paypal');
})();

(function () {
    const simpleForm = document.getElementById('scheduler-simple-form');
    const advancedForm = document.getElementById('scheduler-advanced-form');
    const simpleButton = document.getElementById('scheduler-mode-simple-btn');
    const advancedButton = document.getElementById('scheduler-mode-advanced-btn');

    if (!simpleForm || !advancedForm || !simpleButton || !advancedButton) {
        return;
    }

    const isAdvancedVisible = advancedForm.style.display !== 'none';
    simpleButton.style.opacity = isAdvancedVisible ? '0.75' : '1';
    advancedButton.style.opacity = isAdvancedVisible ? '1' : '0.75';
})();


