/**
 * GrapesJS RSS Import Plugin
 * Registers as a Mautic GrapesJS plugin without modifying core
 */
(function() {
    'use strict';

    console.log('RSS Import: Script loaded, registering plugin...');

    // RSS Import Plugin for GrapesJS
    const rssImportPlugin = function(editor, opts = {}) {
        let rssData = null;
        let htmlTemplate = '';

        // Add command to fetch and show RSS modal
        editor.Commands.add('rss-import', {
            run: async function(editor, sender) {
                showLoadingModal();

                try {
                    // Use absolute path from root or construct with window.location.origin
                    const fetchUrl = window.location.origin + '/s/emailrssimport/fetch';
                    console.log('RSS Import: Fetching from:', fetchUrl);

                    const response = await fetch(fetchUrl, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    const data = await response.json();

                    if (data.error) {
                        alert('Error fetching RSS feed: ' + data.error);
                        closeModal();
                        return;
                    }

                    rssData = data.items;
                    htmlTemplate = data.template;
                    showRssModal(data.items, editor);
                } catch (error) {
                    alert('Error: ' + error.message);
                    closeModal();
                }
            }
        });

        // Add block to blocks panel (left sidebar)
        editor.BlockManager.add('rss-import-block', {
            label: 'RSS Feed',
            category: 'Extra',
            content: '',
            media: '<i class="fa fa-rss" style="font-size: 32px; color: #ff6600;"></i>',
            activate: true,
            select: true,
            attributes: {
                title: 'Import RSS Feed Items'
            },
            // When block is clicked or dragged, open the RSS modal
            onClick: function() {
                editor.runCommand('rss-import');
            }
        });

        function showLoadingModal() {
            const modalHtml = `
                <div class="modal fade in" id="rss-import-modal" style="display: block; z-index: 10000;">
                    <div class="modal-backdrop fade in" style="z-index: 9999;"></div>
                    <div class="modal-dialog modal-lg" style="z-index: 10001;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" onclick="document.getElementById('rss-import-modal').remove()">
                                    <span>&times;</span>
                                </button>
                                <h4 class="modal-title">RSS Feed Import</h4>
                            </div>
                            <div class="modal-body">
                                <div class="text-center">
                                    <i class="fa fa-spinner fa-spin fa-3x"></i>
                                    <p class="mt-3">Loading RSS feed...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer.firstElementChild);
        }

        function showRssModal(items, editor) {
            if (!items || items.length === 0) {
                alert('No items found in RSS feed');
                closeModal();
                return;
            }

            const itemsHtml = items.map(function(item, index) {
                const title = item.title || 'Untitled';
                let description = item.description || '';
                const pubDate = item.pubDate || '';

                // Truncate and strip HTML from description
                if (description.length > 150) {
                    description = description.substring(0, 150) + '...';
                }
                description = description.replace(/<[^>]*>/g, '');

                return `
                    <div class="rss-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px;">
                        <label style="display: block; cursor: pointer; margin: 0;">
                            <input type="checkbox" class="rss-item-checkbox" value="${index}" style="margin-right: 10px;">
                            <strong>${title}</strong>
                            ${pubDate ? '<small class="text-muted" style="margin-left: 10px;">' + pubDate + '</small>' : ''}
                            ${description ? '<div style="margin-top: 5px; margin-left: 25px; color: #666;">' + description + '</div>' : ''}
                        </label>
                    </div>
                `;
            }).join('');

            const modalHtml = `
                <div class="modal fade in" id="rss-import-modal" style="display: block; z-index: 10000;">
                    <div class="modal-backdrop fade in" style="z-index: 9999;"></div>
                    <div class="modal-dialog modal-lg" style="z-index: 10001;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" onclick="document.getElementById('rss-import-modal').remove()">
                                    <span>&times;</span>
                                </button>
                                <h4 class="modal-title">Select RSS Items to Import</h4>
                            </div>
                            <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                                <div class="mb-3">
                                    <button type="button" class="btn btn-default btn-sm" id="select-all-items">Select All</button>
                                    <button type="button" class="btn btn-default btn-sm" id="deselect-all-items">Deselect All</button>
                                </div>
                                <div id="rss-items-list">
                                    ${itemsHtml}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" onclick="document.getElementById('rss-import-modal').remove()">Cancel</button>
                                <button type="button" class="btn btn-primary" id="insert-rss-items">Insert Selected Items</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            closeModal();

            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer.firstElementChild);

            document.getElementById('select-all-items').addEventListener('click', function() {
                document.querySelectorAll('.rss-item-checkbox').forEach(function(cb) {
                    cb.checked = true;
                });
            });

            document.getElementById('deselect-all-items').addEventListener('click', function() {
                document.querySelectorAll('.rss-item-checkbox').forEach(function(cb) {
                    cb.checked = false;
                });
            });

            document.getElementById('insert-rss-items').addEventListener('click', function() {
                insertSelectedItems(editor);
            });
        }

        function insertSelectedItems(editor) {
            const checkboxes = document.querySelectorAll('.rss-item-checkbox:checked');

            if (checkboxes.length === 0) {
                alert('Please select at least one item to insert');
                return;
            }

            const selectedIndices = Array.from(checkboxes)
                .map(function(cb) { return parseInt(cb.value); })
                .sort(function(a, b) { return a - b; });

            let htmlContent = '';

            selectedIndices.forEach(function(index) {
                const item = rssData[index];
                let itemHtml = htmlTemplate;

                // Replace tokens
                Object.keys(item).forEach(function(key) {
                    const token = '{' + key + '}';
                    const value = item[key] || '';
                    itemHtml = itemHtml.split(token).join(value);
                });

                htmlContent += itemHtml + '\n';
            });

            // Insert content into GrapesJS editor
            try {
                const wrapper = editor.getWrapper();
                editor.addComponents(htmlContent, { at: wrapper.components().length });
                console.log('RSS content inserted successfully');
            } catch (error) {
                console.error('Error inserting content:', error);
                alert('Error inserting content: ' + error.message);
            }

            closeModal();
        }

        function closeModal() {
            const modal = document.getElementById('rss-import-modal');
            if (modal) {
                modal.remove();
            }
        }
    };

    // Register plugin with Mautic GrapesJS extension system
    if (!window.MauticGrapesJsPlugins) {
        window.MauticGrapesJsPlugins = [];
    }

    window.MauticGrapesJsPlugins.push({
        name: 'mautic-rss-import',
        plugin: rssImportPlugin
    });

    console.log('RSS Import plugin registered with Mautic GrapesJS');
})();
