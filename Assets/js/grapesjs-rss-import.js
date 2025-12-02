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
        let availableFeeds = [];
        let currentFeedName = '';

        // Add command to fetch and show RSS modal
        editor.Commands.add('rss-import', {
            run: async function(editor, sender) {
                showLoadingModal();

                try {
                    // First, fetch the list of available feeds
                    const listUrl = window.location.origin + '/s/emailrssimport/fetch?list_feeds=1';
                    console.log('RSS Import: Fetching feed list from:', listUrl);

                    const listResponse = await fetch(listUrl, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    const listData = await listResponse.json();

                    if (listData.error) {
                        alert('Error fetching feed list: ' + listData.error);
                        closeModal();
                        return;
                    }

                    availableFeeds = listData.feeds || [];

                    // If only one feed, fetch it directly
                    if (availableFeeds.length === 1) {
                        await fetchFeed(availableFeeds[0], editor);
                    } else {
                        // Show feed selector modal
                        showFeedSelectorModal(editor);
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                    closeModal();
                }
            }
        });

        async function fetchFeed(feedName, editor) {
            showLoadingModal();

            try {
                const fetchUrl = window.location.origin + '/s/emailrssimport/fetch?feed=' + encodeURIComponent(feedName);
                console.log('RSS Import: Fetching feed:', feedName, 'from:', fetchUrl);

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
                currentFeedName = data.feedName || feedName;
                showRssModal(data.items, editor);
            } catch (error) {
                alert('Error: ' + error.message);
                closeModal();
            }
        }

        // Define a custom component type for RSS import placeholder
        editor.DomComponents.addType('rss-import-placeholder', {
            model: {
                defaults: {
                    tagName: 'div',
                    draggable: true,
                    droppable: false,
                    removable: true,
                    copyable: false,
                    attributes: { 'data-rss-placeholder': 'true' },
                    content: '<p style="text-align: center; padding: 20px; background: #f0f0f0; border: 2px dashed #ccc;">Loading RSS Feed...</p>',
                }
            }
        });

        // Track if modal is already open to prevent duplicates
        let isModalOpen = false;
        let currentPlaceholder = null;

        // Listen for component added event
        editor.on('component:add', function(component) {
            // Check if it's our RSS placeholder
            if (component.get('type') === 'rss-import-placeholder' && !isModalOpen) {
                isModalOpen = true;
                currentPlaceholder = component;

                // Small delay to ensure component is properly added to canvas
                setTimeout(function() {
                    editor.runCommand('rss-import');
                }, 100);
            }
        });

        // Add block to blocks panel (left sidebar)
        editor.BlockManager.add('rss-import-block', {
            label: 'RSS Feed',
            category: 'Extra',
            content: { type: 'rss-import-placeholder' },
            media: '<i class="fa fa-rss" style="font-size: 32px; color: #ff6600;"></i>',
            attributes: {
                title: 'Drag to import RSS Feed Items'
            }
        });

        function showFeedSelectorModal(editor) {
            const feedOptions = availableFeeds.map(function(feedName) {
                return `<option value="${feedName}">${feedName}</option>`;
            }).join('');

            const modalHtml = `
                <div class="modal fade in" id="rss-import-modal" style="display: block; z-index: 10000;">
                    <div class="modal-backdrop fade in" style="z-index: 9999;"></div>
                    <div class="modal-dialog" style="z-index: 10001;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" id="feed-selector-close-btn">
                                    <span>&times;</span>
                                </button>
                                <h4 class="modal-title">Select RSS Feed</h4>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="feed-selector">Choose an RSS feed to import:</label>
                                    <select class="form-control" id="feed-selector">
                                        ${feedOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" id="feed-selector-cancel-btn">Cancel</button>
                                <button type="button" class="btn btn-primary" id="feed-selector-load-btn">Load Feed</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove any existing modal first
            const existingModal = document.getElementById('rss-import-modal');
            if (existingModal) {
                existingModal.remove();
            }

            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer.firstElementChild);

            // Attach event listeners
            document.getElementById('feed-selector-close-btn').addEventListener('click', closeModal);
            document.getElementById('feed-selector-cancel-btn').addEventListener('click', closeModal);
            document.getElementById('feed-selector-load-btn').addEventListener('click', function() {
                const selectedFeed = document.getElementById('feed-selector').value;
                fetchFeed(selectedFeed, editor);
            });
        }

        function showLoadingModal() {
            const modalHtml = `
                <div class="modal fade in" id="rss-import-modal" style="display: block; z-index: 10000;">
                    <div class="modal-backdrop fade in" style="z-index: 9999;"></div>
                    <div class="modal-dialog modal-lg" style="z-index: 10001;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" id="loading-modal-close-btn">
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

            // Remove any existing modal first
            const existingModal = document.getElementById('rss-import-modal');
            if (existingModal) {
                existingModal.remove();
            }

            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer.firstElementChild);

            // Attach event listener for close button
            setTimeout(function() {
                const closeBtn = document.getElementById('loading-modal-close-btn');
                if (closeBtn) {
                    closeBtn.addEventListener('click', closeModal);
                }
            }, 0);
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

            const feedTitle = currentFeedName ? ' - ' + currentFeedName : '';
            const modalHtml = `
                <div class="modal fade in" id="rss-import-modal" style="display: block; z-index: 10000;">
                    <div class="modal-backdrop fade in" style="z-index: 9999;"></div>
                    <div class="modal-dialog modal-lg" style="z-index: 10001;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" id="modal-close-btn">
                                    <span>&times;</span>
                                </button>
                                <h4 class="modal-title">Select RSS Items to Import${feedTitle}</h4>
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
                                <button type="button" class="btn btn-default" id="modal-cancel-btn">Cancel</button>
                                <button type="button" class="btn btn-primary" id="insert-rss-items">Insert Selected Items</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove any existing modal first
            const existingModal = document.getElementById('rss-import-modal');
            if (existingModal) {
                existingModal.remove();
            }

            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer.firstElementChild);

            // Attach event listeners
            document.getElementById('modal-close-btn').addEventListener('click', closeModal);
            document.getElementById('modal-cancel-btn').addEventListener('click', closeModal);

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
                if (currentPlaceholder && currentPlaceholder.parent()) {
                    // Get the parent and index of placeholder
                    const parent = currentPlaceholder.parent();
                    const index = parent.components().indexOf(currentPlaceholder);

                    // Remove placeholder
                    currentPlaceholder.remove();

                    // Add new content at the same position
                    parent.append(htmlContent, { at: index });
                    console.log('RSS content inserted successfully at placeholder position');
                } else {
                    // Fallback: insert at end if placeholder not found
                    const wrapper = editor.getWrapper();
                    editor.addComponents(htmlContent, { at: wrapper.components().length });
                    console.log('RSS content inserted successfully');
                }
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

            // If modal is closed without selecting items, remove the placeholder
            if (currentPlaceholder && currentPlaceholder.parent()) {
                currentPlaceholder.remove();
            }

            // Reset state
            isModalOpen = false;
            currentPlaceholder = null;
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
