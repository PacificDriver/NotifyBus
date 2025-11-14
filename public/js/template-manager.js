;(function (window, document) {
    class TemplateManager {
        constructor(options = {}) {
            this.root = options.root;
            this.listSelector = options.listSelector;
            this.createFormSelector = options.createFormSelector;
            this.toggleSelector = options.toggleSelector;
            this.emptyText = options.emptyText || 'Шаблоны не найдены.';
            this.apiBase = options.apiBase || '/api/templates';
            this.templates = [];
            this.loading = false;
            this.token =
                document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        init() {
            if (!this.root) {
                return;
            }

            this.listElement = this.root.querySelector(this.listSelector);
            this.createForm = this.root.querySelector(this.createFormSelector);
            this.toggleButton = this.root.querySelector(this.toggleSelector);

            if (this.createForm) {
                this.createForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    this.handleCreate(new FormData(this.createForm));
                });

                this.setupSlugAutoFill(this.createForm);
            }

            if (this.toggleButton && this.createForm) {
                this.toggleButton.addEventListener('click', () => {
                    this.createForm.classList.toggle('hidden');
                });
            }

            this.loadTemplates();
        }

        async loadTemplates() {
            if (!this.listElement) return;
            this.loading = true;
            this.listElement.innerHTML =
                '<div class="alert alert-info">Загрузка шаблонов...</div>';

            try {
                const response = await fetch(`${this.apiBase}?active_only=0`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message ||
                            `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                this.templates = Array.isArray(data.data) ? data.data : [];
                this.renderTemplates();
            } catch (error) {
                console.error('Failed to load templates:', error);
                this.listElement.innerHTML = `<div class="alert alert-error">Не удалось загрузить шаблоны: ${this.escapeHtml(
                    error.message
                )}</div>`;
            } finally {
                this.loading = false;
            }
        }

        renderTemplates() {
            if (!this.listElement) return;

            if (!this.templates.length) {
                this.listElement.innerHTML = `<div class="alert alert-info">${this.escapeHtml(
                    this.emptyText
                )}</div>`;
                return;
            }

            const cards = this.templates
                .map((template) => this.renderTemplateCard(template))
                .join('');

            this.listElement.innerHTML = cards;

            this.listElement
                .querySelectorAll('.template-edit-form')
                .forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        event.preventDefault();
                        const templateId = form.dataset.templateId;
                        this.handleUpdate(templateId, new FormData(form));
                    });

                    this.setupSlugAutoFill(form);
                });

            this.listElement
                .querySelectorAll('[data-template-action="delete"]')
                .forEach((button) => {
                    button.addEventListener('click', () => {
                        const templateId = button.dataset.templateId;
                        const templateName = button.dataset.templateName;
                        this.handleDelete(templateId, templateName);
                    });
                });
        }

        renderTemplateCard(template) {
            const variables =
                template.available_variables && template.available_variables.length
                    ? template.available_variables.join(', ')
                    : '—';

            const typeLabels = {
                cancellation: 'Отмена рейса',
                delay: 'Задержка рейса',
                general: 'Общий шаблон',
            };

            const badgeClass = template.is_active
                ? 'badge badge-success'
                : 'badge badge-warning';

            return `
                <div class="template-card">
                    <form class="template-edit-form" data-template-id="${
                        template.id
                    }">
                        <div class="template-card-header">
                            <div>
                                <h3>${this.escapeHtml(template.name)}</h3>
                                <p class="muted-text">
                                    ${this.escapeHtml(
                                        typeLabels[template.type] || template.type
                                    )}
                                    · <code>${this.escapeHtml(template.slug)}</code>
                                </p>
                            </div>
                            <span class="${badgeClass}">
                                ${template.is_active ? 'Активен' : 'Выключен'}
                            </span>
                        </div>
                        <div class="template-form-grid">
                            <label>
                                Название
                                <input type="text" name="name" required value="${this.escapeHtml(
                                    template.name
                                )}" data-template-field="name">
                            </label>
                            <label>
                                Слаг
                                <input type="text" name="slug" required value="${this.escapeHtml(
                                    template.slug
                                )}" data-template-field="slug">
                            </label>
                            <label>
                                Тип
                                ${this.renderTypeSelect(template.type)}
                            </label>
                            <label>
                                Тема письма (для Email)
                                <input type="text" name="subject" value="${this.escapeHtml(
                                    template.subject || ''
                                )}">
                            </label>
                            <label class="full-width">
                                Тело сообщения
                                <textarea name="body" rows="4" required>${this.escapeHtml(
                                    template.body || ''
                                )}</textarea>
                            </label>
                            <label class="full-width">
                                Доступные переменные (через запятую)
                                <input type="text" name="available_variables" value="${this.escapeHtml(
                                    variables !== '—' ? variables : ''
                                )}">
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="is_active" ${
                                    template.is_active ? 'checked' : ''
                                }>
                                Шаблон активен
                            </label>
                        </div>
                        <div class="template-actions">
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                            <button type="button" class="btn btn-danger" data-template-action="delete" data-template-id="${
                                template.id
                            }" data-template-name="${this.escapeHtml(
                template.name
            )}">Удалить</button>
                        </div>
                    </form>
                </div>
            `;
        }

        renderTypeSelect(selected) {
            const options = [
                { value: 'cancellation', label: 'Отмена рейса' },
                { value: 'delay', label: 'Задержка рейса' },
                { value: 'general', label: 'Общий шаблон' },
            ];

            return `
                <select name="type" required>
                    ${options
                        .map(
                            (option) => `
                        <option value="${option.value}" ${
                                option.value === selected ? 'selected' : ''
                            }>${option.label}</option>`
                        )
                        .join('')}
                </select>
            `;
        }

        async handleCreate(formData) {
            const payload = this.buildPayload(formData, {
                requireSlug: true,
            });

            try {
                const response = await fetch(this.apiBase, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message ||
                            `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                this.createForm?.reset();
                this.loadTemplates();
                this.showToast('Шаблон создан успешно', 'success');
            } catch (error) {
                this.showToast(
                    'Ошибка создания шаблона: ' + error.message,
                    'error'
                );
            }
        }

        async handleUpdate(templateId, formData) {
            const payload = this.buildPayload(formData);

            try {
                const response = await fetch(`${this.apiBase}/${templateId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message ||
                            `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                this.showToast('Шаблон обновлен', 'success');
                this.loadTemplates();
            } catch (error) {
                this.showToast(
                    'Ошибка обновления шаблона: ' + error.message,
                    'error'
                );
            }
        }

        async handleDelete(templateId, templateName = '') {
            if (
                !window.confirm(
                    `Удалить шаблон "${templateName || templateId}"? Это действие нельзя отменить.`
                )
            ) {
                return;
            }

            try {
                const response = await fetch(`${this.apiBase}/${templateId}`, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message ||
                            `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                this.showToast('Шаблон удален', 'success');
                this.loadTemplates();
            } catch (error) {
                this.showToast(
                    'Ошибка удаления шаблона: ' + error.message,
                    'error'
                );
            }
        }

        buildPayload(formData, options = {}) {
            const payload = {};

            formData.forEach((value, key) => {
                if (typeof value === 'string') {
                    value = value.trim();
                }

                if (key === 'available_variables') {
                    payload[key] = this.splitVariables(value);
                    return;
                }

                payload[key] = value;
            });

            if ('is_active' in payload) {
                payload.is_active =
                    payload.is_active === 'on' ||
                    payload.is_active === 'true' ||
                    payload.is_active === true;
            }

            if (payload.slug || options.requireSlug) {
                payload.slug = this.slugify(payload.slug || '');
            }

            return payload;
        }

        splitVariables(value) {
            if (!value) return [];

            return value
                .split(/[,;\n]/)
                .map((item) => item.trim())
                .filter(Boolean);
        }

        slugify(value) {
            return (value || '')
                .toString()
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9а-яё\s_-]/gi, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
        }

        setupSlugAutoFill(form) {
            const nameInput = form.querySelector('[data-template-field="name"]');
            const slugInput = form.querySelector('[data-template-field="slug"]');

            if (!nameInput || !slugInput) {
                return;
            }

            let slugEdited = slugInput.value.trim().length > 0;

            slugInput.addEventListener('input', () => {
                slugEdited = slugInput.value.trim().length > 0;
            });

            nameInput.addEventListener('input', () => {
                if (slugEdited) {
                    return;
                }
                slugInput.value = this.slugify(nameInput.value);
            });
        }

        escapeHtml(value) {
            if (value === undefined || value === null) {
                return '';
            }

            return value
                .toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        showToast(message, type = 'info') {
            if (window.showModal) {
                window.showModal({
                    type,
                    title:
                        type === 'success'
                            ? 'Успешно'
                            : type === 'error'
                              ? 'Ошибка'
                              : 'Информация',
                    message,
                });
                return;
            }

            console.log(`[${type}] ${message}`);
        }
    }

    window.TemplateManager = TemplateManager;
})(window, document);


