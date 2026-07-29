/**
 * Media Maestro - Folders Organizer UI Refactor
 *
 * Extends the WordPress Media Library grid view to add a native folder sidebar panel
 * in a two-column page layout, support drag-and-drop, real-time search, and active folder controls.
 */
(function ($, _) {
    'use strict';

    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        return;
    }

    if (typeof mm_folders_data === 'undefined') {
        console.error('Media Maestro: mm_folders_data missing');
        return;
    }

    var media = wp.media;

    // Extend wp.media.view.Attachment to add data-id attribute to list items in the media grid
    if (media.view.Attachment) {
        var originalAttachment = media.view.Attachment;
        media.view.Attachment = originalAttachment.extend({
            attributes: function () {
                var attrs = {};
                if (originalAttachment.prototype.attributes) {
                    if (typeof originalAttachment.prototype.attributes === 'function') {
                        attrs = originalAttachment.prototype.attributes.apply(this, arguments);
                    } else {
                        attrs = _.clone(originalAttachment.prototype.attributes);
                    }
                }
                attrs['data-id'] = this.model.get('id');
                return attrs;
            }
        });
    }

    // Also extend wp.media.view.Attachment.Library just in case it was already compiled/extended
    if (media.view.Attachment.Library) {
        var originalAttachmentLibrary = media.view.Attachment.Library;
        media.view.Attachment.Library = originalAttachmentLibrary.extend({
            attributes: function () {
                var attrs = {};
                if (originalAttachmentLibrary.prototype.attributes) {
                    if (typeof originalAttachmentLibrary.prototype.attributes === 'function') {
                        attrs = originalAttachmentLibrary.prototype.attributes.apply(this, arguments);
                    } else {
                        attrs = _.clone(originalAttachmentLibrary.prototype.attributes);
                    }
                }
                attrs['data-id'] = this.model.get('id');
                return attrs;
            }
        });
    }

    /**
     * Folder Sidebar View
     */
    media.view.MediaMaestroFolderSidebar = media.View.extend({
        className: 'mm-folders-sidebar',

        events: {
            'click .mm-btn-add-folder': 'createFolder',
            'click .rename-btn': 'renameFolder',
            'click .delete-btn': 'deleteFolder',
            'click .mm-btn-settings': 'openSettings',
            'click .clear-btn': 'clearFilter',
            'click .toggle-btn': 'toggleCollapse',
            'input .mm-folder-search-input': 'onSearchFolders',
            'click .mm-folder-item': 'selectFolder'
        },

        initialize: function (options) {
            this.browser = options.browser;
            this.controller = options.controller;
            this.currentFolder = ''; // default "All Files"
            this.folders = [];
            this.counts = { unassigned: 0, all: 0 };
            this.isCollapsed = false;

            // Fetch initial folders list from server
            this.fetchFolders();

            // Rerender when collection resets/syncs to keep item counts updated
            this.listenTo(this.browser.collection, 'sync reset add remove', this.updateCountsThrottled);
        },

        fetchFolders: function () {
            var self = this;
            $.ajax({
                url: mm_folders_data.api_url,
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', mm_folders_data.nonce);
                }
            }).done(function (response) {
                if (response && response.folders) {
                    self.folders = response.folders;
                    self.counts.unassigned = response.unassigned;
                    self.counts.all = response.all;
                    self.render();
                }
            });
        },

        updateCountsThrottled: _.debounce(function () {
            this.fetchFolders();
        }, 1500),

        render: function () {
            var self = this;
            var html = '';

            // 1. Header Row
            html += '<div class="mm-folders-header">';
            html += '  <span class="mm-folders-title">Media Library Organizer</span>';
            html += '  <button type="button" class="mm-btn-settings" title="Settings">&#9881;</button>';
            html += '</div>';

            // 2. Action Rows Section
            html += '<div class="mm-folders-actions-section">';
            html += '  <div class="mm-action-row-1">';
            html += '    <button type="button" class="button button-primary mm-btn-add-folder">+ New Folder</button>';
            html += '    <div class="mm-action-group-right">';
            html += '      <button type="button" class="button mm-action-small-btn sort-btn" title="Sort Folders (PRO)" disabled><span class="mm-pro-badge">PRO</span>&#8645;</button>';
            html += '      <button type="button" class="button mm-action-small-btn clear-btn" title="Clear Filter">&times;</button>';
            html += '      <button type="button" class="button mm-action-small-btn toggle-btn" title="Toggle Folder List">&#8597;</button>';
            html += '    </div>';
            html += '  </div>';

            // Row 2: Rename, Delete, Bulk select
            var isCustomFolder = (this.currentFolder !== '' && this.currentFolder !== 'unassigned');
            var disabledAttr = isCustomFolder ? '' : ' disabled';
            var disabledClass = isCustomFolder ? '' : ' disabled';

            html += '  <div class="mm-action-row-2">';
            html += '    <button type="button" class="button rename-btn' + disabledClass + '"' + disabledAttr + '>&#9998; Rename</button>';
            html += '    <button type="button" class="button delete-btn' + disabledClass + '"' + disabledAttr + '>&#128465; Delete</button>';
            html += '    <button type="button" class="button bulk-select-btn">&#9745; Bulk select</button>';
            html += '  </div>';
            html += '</div>';

            // 3. Folder Search Section
            html += '<div class="mm-folders-search-section">';
            html += '  <span class="mm-search-icon">&#128269;</span>';
            html += '  <input type="search" class="mm-folder-search-input" placeholder="Find folder..." />';
            html += '</div>';

            // 4. Folder List
            var listCollapsedClass = this.isCollapsed ? ' mm-collapsed' : '';
            html += '<ul class="mm-folders-list' + listCollapsedClass + '">';
            
            // All Files Folder
            var allActive = (this.currentFolder === '') ? ' active' : '';
            html += '  <li class="mm-folder-item' + allActive + '" data-folder-id="">';
            html += '    <span class="mm-folder-icon">&#128196;</span>'; // Paper/file icon
            html += '    <span class="mm-folder-name">All Files</span>';
            html += '    <span class="mm-folder-count">' + this.counts.all + '</span>';
            html += '  </li>';

            // Uncategorized Folder
            var unassignedActive = (this.currentFolder === 'unassigned') ? ' active' : '';
            html += '  <li class="mm-folder-item' + unassignedActive + '" data-folder-id="unassigned">';
            html += '    <span class="mm-folder-icon">&#128194;&#215;</span>'; // Folder with cross
            html += '    <span class="mm-folder-name">Uncategorized</span>';
            html += '    <span class="mm-folder-count">' + this.counts.unassigned + '</span>';
            html += '  </li>';

            // Custom Folders Header Label
            html += '  <li class="mm-folder-section-heading">FOLDERS</li>';

            // User Custom Folders
            _.each(this.folders, function (folder) {
                var folderActive = (self.currentFolder == folder.id) ? ' active' : '';
                html += '  <li class="mm-folder-item' + folderActive + '" data-folder-id="' + folder.id + '">';
                html += '    <span class="mm-folder-icon">&#128194;</span>'; // Folder icon
                html += '    <span class="mm-folder-name">' + _.escape(folder.name) + '</span>';
                html += '    <span class="mm-folder-count">' + folder.count + '</span>';
                html += '  </li>';
            });

            html += '</ul>';

            this.$el.html(html);

            // Bind droppable targets
            this.bindDroppables();

            return this;
        },

        selectFolder: function (e) {
            var $target = $(e.currentTarget);
            var folderId = $target.data('folder-id');

            this.currentFolder = folderId;
            this.$('.mm-folder-item').removeClass('active');
            $target.addClass('active');

            // Apply filter to query props
            this.browser.collection.props.set({ mm_folder: folderId });
            
            // Toggle action buttons disabled state based on active selection
            var isCustomFolder = (folderId !== '' && folderId !== 'unassigned');
            this.$('.rename-btn, .delete-btn')
                .prop('disabled', !isCustomFolder)
                .toggleClass('disabled', !isCustomFolder);

            // Set active upload folder params
            if (wp.media.uploader && wp.media.uploader.options && wp.media.uploader.options.uploader) {
                wp.media.uploader.options.uploader.params = _.extend(
                    wp.media.uploader.options.uploader.params || {},
                    { mm_folder: folderId }
                );
            }
        },

        showModal: function (options) {
            var self = this;
            
            // Remove existing modal if any
            $('.mm-modal-backdrop').remove();

            var modalHtml = 
                '<div class="mm-modal-backdrop">' +
                '  <div class="mm-modal-container">' +
                '    <div class="mm-modal-header">' +
                '      <h3 class="mm-modal-title">' + _.escape(options.title) + '</h3>' +
                '      <button class="mm-modal-close">&times;</button>' +
                '    </div>' +
                '    <div class="mm-modal-body">' +
                '      <input type="text" id="mm-folder-name-input" class="mm-modal-input" placeholder="' + _.escape(options.placeholder || '') + '" value="' + _.escape(options.value || '') + '" autocomplete="off" />' +
                '      <div class="mm-modal-error-message" style="color: #dc2626; font-size: 12px; margin-top: 5px; display: none;"></div>' +
                '    </div>' +
                '    <div class="mm-modal-footer">' +
                '      <button type="button" class="button mm-modal-btn-cancel">Cancel</button>' +
                '      <button type="button" class="button button-primary mm-modal-btn-submit">' + _.escape(options.submitText || 'Submit') + '</button>' +
                '    </div>' +
                '  </div>' +
                '</div>';

            var $modal = $(modalHtml).appendTo('body');

            // Animate in
            setTimeout(function () {
                $modal.addClass('open');
                $modal.find('#mm-folder-name-input').focus().select();
            }, 10);

            // Event handlers
            var closeModal = function () {
                $modal.removeClass('open');
                setTimeout(function () {
                    $modal.remove();
                }, 300);
            };

            $modal.find('.mm-modal-close, .mm-modal-btn-cancel').on('click', function (e) {
                e.preventDefault();
                closeModal();
            });

            $modal.on('click', function (e) {
                if ($(e.target).hasClass('mm-modal-backdrop')) {
                    closeModal();
                }
            });

            var submit = function () {
                var $input = $modal.find('#mm-folder-name-input');
                var val = $.trim($input.val());
                var $error = $modal.find('.mm-modal-error-message');
                $error.hide().text('');

                if (val === '') {
                    $error.text('Name cannot be empty.').show();
                    $input.focus();
                    return;
                }

                $modal.find('.button').prop('disabled', true);
                
                options.onSubmit(val, function (err) {
                    if (err) {
                        $modal.find('.button').prop('disabled', false);
                        $error.text(err).show();
                        $input.focus();
                    } else {
                        closeModal();
                    }
                });
            };

            $modal.find('.mm-modal-btn-submit').on('click', function (e) {
                e.preventDefault();
                submit();
            });

            $modal.find('#mm-folder-name-input').on('keydown', function (e) {
                if (e.which === 13) { // Enter
                    e.preventDefault();
                    submit();
                } else if (e.which === 27) { // Escape
                    e.preventDefault();
                    closeModal();
                }
            });
        },

        createFolder: function (e) {
            e.preventDefault();
            e.stopPropagation();

            var self = this;

            this.showModal({
                title: 'Create New Folder',
                placeholder: 'Folder Name',
                submitText: 'Create',
                onSubmit: function (name, callback) {
                    $.ajax({
                        url: mm_folders_data.api_url,
                        method: 'POST',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', mm_folders_data.nonce);
                        },
                        data: {
                            name: name
                        }
                    }).done(function () {
                        self.fetchFolders();
                        callback();
                    }).fail(function (xhr) {
                        var error = xhr.responseJSON ? xhr.responseJSON.message : 'Error creating folder.';
                        callback(error);
                    });
                }
            });
        },

        renameFolder: function (e) {
            e.preventDefault();
            e.stopPropagation();

            var self = this;
            var folderId = this.currentFolder;
            if (folderId === '' || folderId === 'unassigned') {
                return;
            }

            var oldName = this.$('.mm-folder-item.active .mm-folder-name').text();

            this.showModal({
                title: 'Rename Folder',
                placeholder: 'Folder Name',
                value: oldName,
                submitText: 'Rename',
                onSubmit: function (name, callback) {
                    if (name === oldName) {
                        callback();
                        return;
                    }

                    $.ajax({
                        url: mm_folders_data.api_url + '/' + folderId,
                        method: 'POST',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', mm_folders_data.nonce);
                        },
                        data: {
                            name: name
                        }
                    }).done(function () {
                        self.fetchFolders();
                        callback();
                    }).fail(function (xhr) {
                        var error = xhr.responseJSON ? xhr.responseJSON.message : 'Error renaming folder.';
                        callback(error);
                    });
                }
            });
        },

        deleteFolder: function (e) {
            e.preventDefault();
            e.stopPropagation();

            var self = this;
            var folderId = this.currentFolder;
            if (folderId === '' || folderId === 'unassigned') {
                return;
            }

            if (!confirm('Are you sure you want to delete this folder? Media items inside will not be deleted.')) {
                return;
            }

            $.ajax({
                url: mm_folders_data.api_url + '/' + folderId,
                method: 'DELETE',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', mm_folders_data.nonce);
                }
            }).done(function () {
                self.currentFolder = '';
                self.browser.collection.props.set({ mm_folder: '' });
                self.fetchFolders();
            }).fail(function (xhr) {
                var error = xhr.responseJSON ? xhr.responseJSON.message : 'Error deleting folder.';
                alert(error);
            });
        },

        openSettings: function (e) {
            e.preventDefault();
            window.location.href = 'options-general.php?page=media-maestro';
        },

        clearFilter: function (e) {
            e.preventDefault();
            this.currentFolder = '';
            this.$('.mm-folder-item').removeClass('active');
            this.$('.mm-folder-item[data-folder-id=""]').addClass('active');
            this.browser.collection.props.set({ mm_folder: '' });
            this.$('.rename-btn, .delete-btn').addClass('disabled').prop('disabled', true);
        },

        toggleCollapse: function (e) {
            e.preventDefault();
            this.isCollapsed = !this.isCollapsed;
            this.$('.mm-folders-list').toggleClass('mm-collapsed', this.isCollapsed);
        },

        onSearchFolders: function (e) {
            var query = $(e.currentTarget).val().toLowerCase();
            this.$('.mm-folders-list .mm-folder-item').each(function () {
                var $item = $(this);
                var name = $item.find('.mm-folder-name').text().toLowerCase();
                
                if (name.indexOf(query) !== -1) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });

            // Handle heading display visibility based on query
            if (query !== '') {
                this.$('.mm-folder-section-heading').hide();
            } else {
                this.$('.mm-folder-section-heading').show();
            }
        },

        bindDroppables: function () {
            var self = this;
            this.$('.mm-folder-item').droppable({
                accept: '.attachment',
                hoverClass: 'mm-folder-hover',
                tolerance: 'pointer',
                drop: function (event, ui) {
                    var folderId = $(this).data('folder-id');
                    var draggedId = parseInt(ui.draggable.attr('data-id') || ui.draggable.data('id') || ui.helper.data('attachment-id'), 10);
                    var ids = [draggedId];

                    console.log('Media Maestro Folder Drop:', {
                        folderId: folderId,
                        draggedId: draggedId,
                        ids: ids
                    });

                    // Check if dragged item is part of bulk selection
                    var selection = self.controller.state().get('selection');
                    if (selection && selection.length > 0) {
                        var selectionIds = selection.pluck('id');
                        if (_.contains(selectionIds, draggedId)) {
                            ids = selectionIds;
                        }
                    }

                    // Show visual loading indicator state on the droppable target folder item
                    var $folderItem = $(this);
                    $folderItem.addClass('mm-folder-loading');

                    // Assign to folder on server
                    $.ajax({
                        url: mm_folders_data.api_url + '/assign',
                        method: 'POST',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', mm_folders_data.nonce);
                        },
                        data: {
                            attachment_ids: ids,
                            folder_id: folderId
                        }
                    }).done(function (response) {
                        $folderItem.removeClass('mm-folder-loading');
                        if (response && response.folders) {
                            self.folders = response.folders;
                            self.counts.unassigned = response.unassigned;
                            self.counts.all = response.all;
                            self.render();
                        }
                        
                        // Reset selection
                        if (selection) {
                            selection.reset();
                        }

                        // Refresh attachment grid items
                        if (typeof self.browser.collection._requery === 'function') {
                            self.browser.collection._requery(true);
                        } else {
                            self.browser.collection.props.trigger('change');
                        }
                    }).fail(function () {
                        $folderItem.removeClass('mm-folder-loading');
                        alert('Failed to assign media to folder.');
                    });
                }
            });
        }
    });

    /**
     * Intercept AttachmentsBrowser initialization
     */
    var originalAttachmentsBrowser = media.view.AttachmentsBrowser;
    media.view.AttachmentsBrowser = originalAttachmentsBrowser.extend({
        initialize: function () {
            originalAttachmentsBrowser.prototype.initialize.apply(this, arguments);

            // Add folders sidebar instance
            this.foldersSidebar = new media.view.MediaMaestroFolderSidebar({
                browser: this,
                controller: this.controller
            });

            this.views.add(this.foldersSidebar);

            // Listen to grid sync events to attach draggable triggers
            this.listenTo(this.collection, 'sync reset add', this.bindDraggables);
        },

        ready: function () {
            originalAttachmentsBrowser.prototype.ready.apply(this, arguments);

            var isStandaloneUploadPage = $('body').hasClass('upload-php');

            if (isStandaloneUploadPage) {
                // Relocate sidebar to wrapper top-level div for native full-page two-column layout
                var $wrap = $('.wrap');
                $wrap.addClass('has-folders-sidebar-wrap');
                $wrap.prepend(this.foldersSidebar.el);
            } else {
                // Nested within modal popup box container
                this.$el.addClass('has-folders-sidebar');
                this.$el.prepend(this.foldersSidebar.el);
            }

            this.foldersSidebar.render();
            this.bindDraggables();
        },

        bindDraggables: function () {
            var self = this;
            setTimeout(function () {
                self.$('.attachments .attachment').each(function () {
                    var $el = $(this);
                    if ($el.data('ui-draggable')) {
                        return; // Already bound
                    }

                    $el.draggable({
                        helper: 'clone',
                        revert: 'invalid',
                        appendTo: 'body',
                        start: function (event, ui) {
                            var id = $(this).attr('data-id');
                            console.log('Media Maestro Drag Start:', {
                                id: id,
                                element: this
                            });
                            ui.helper.data('attachment-id', id);
                            ui.helper.addClass('ui-draggable-dragging');
                        }
                    });
                });
            }, 300);
        }
    });

})(jQuery, _);
