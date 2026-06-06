/**
 * Media Maestro - Folders Organizer
 *
 * Extends the WordPress Media Library grid view to add a native folder sidebar panel,
 * drag-and-drop capability, and list updates.
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

    /**
     * Folder Sidebar View
     */
    media.view.MediaMaestroFolderSidebar = media.View.extend({
        className: 'mm-folders-sidebar',

        events: {
            'click .mm-btn-add-folder': 'createFolder',
            'click .mm-folder-item': 'selectFolder',
            'click .delete-btn': 'deleteFolder',
            'click .rename-btn': 'renameFolder'
        },

        initialize: function (options) {
            this.browser = options.browser;
            this.controller = options.controller;
            this.currentFolder = ''; // default "All Media"
            this.folders = [];
            this.counts = { unassigned: 0, all: 0 };

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

            html += '<div class="mm-folders-title-wrap">';
            html += '  <span class="mm-folders-title">' + mm_folders_data.folders_str + '</span>';
            html += '  <button type="button" class="mm-btn-add-folder" title="' + mm_folders_data.new_folder + '">+</button>';
            html += '</div>';

            html += '<ul class="mm-folders-list">';
            
            // All Media Folder
            var allActive = (this.currentFolder === '') ? ' active' : '';
            html += '  <li class="mm-folder-item' + allActive + '" data-folder-id="">';
            html += '    <span class="mm-folder-name">All Media</span>';
            html += '    <span class="mm-folder-count">' + this.counts.all + '</span>';
            html += '  </li>';

            // Unassigned Folder
            var unassignedActive = (this.currentFolder === 'unassigned') ? ' active' : '';
            html += '  <li class="mm-folder-item' + unassignedActive + '" data-folder-id="unassigned">';
            html += '    <span class="mm-folder-name">Unassigned</span>';
            html += '    <span class="mm-folder-count">' + this.counts.unassigned + '</span>';
            html += '  </li>';

            // User Folders
            _.each(this.folders, function (folder) {
                var folderActive = (self.currentFolder == folder.id) ? ' active' : '';
                html += '  <li class="mm-folder-item' + folderActive + '" data-folder-id="' + folder.id + '">';
                html += '    <span class="mm-folder-name">' + _.escape(folder.name) + '</span>';
                html += '    <span class="mm-folder-count">' + folder.count + '</span>';
                html += '    <div class="mm-folder-actions">';
                html += '      <button type="button" class="mm-folder-action-btn rename-btn" title="Rename">&#9998;</button>';
                html += '      <button type="button" class="mm-folder-action-btn delete-btn" title="Delete">&times;</button>';
                html += '    </div>';
                html += '  </li>';
            });

            html += '</ul>';

            this.$el.html(html);

            // Bind droppable to folders
            this.bindDroppables();

            return this;
        },

        selectFolder: function (e) {
            var $target = $(e.currentTarget);
            var folderId = $target.data('folder-id');

            // Prevent selecting folder if clicking action buttons
            if ($(e.target).hasClass('mm-folder-action-btn')) {
                return;
            }

            this.currentFolder = folderId;
            this.$('.mm-folder-item').removeClass('active');
            $target.addClass('active');

            // Apply filter to collection
            this.browser.collection.props.set({ mm_folder: folderId });
            
            // Set active upload folder
            if (wp.media.uploader && wp.media.uploader.options && wp.media.uploader.options.uploader) {
                wp.media.uploader.options.uploader.params = _.extend(
                    wp.media.uploader.options.uploader.params || {},
                    { mm_folder: folderId }
                );
            }
        },

        createFolder: function (e) {
            e.preventDefault();
            e.stopPropagation();

            var self = this;
            var name = prompt('Enter new folder name:');
            if (!name) {
                return;
            }
            name = $.trim(name);
            if (name === '') {
                alert('Folder name cannot be empty.');
                return;
            }

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
            }).fail(function (xhr) {
                var error = xhr.responseJSON ? xhr.responseJSON.message : 'Error creating folder.';
                alert(error);
            });
        },

        renameFolder: function (e) {
            e.preventDefault();
            e.stopPropagation();

            var self = this;
            var $item = $(e.currentTarget).closest('.mm-folder-item');
            var folderId = $item.data('folder-id');
            var oldName = $item.find('.mm-folder-name').text();

            var name = prompt('Rename folder to:', oldName);
            if (!name) {
                return;
            }
            name = $.trim(name);
            if (name === '' || name === oldName) {
                return;
            }

            $.ajax({
                url: mm_folders_data.api_url + '/' + folderId,
                method: 'POST', // WordPress EDITABLE endpoints map to POST
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', mm_folders_data.nonce);
                },
                data: {
                    name: name
                }
            }).done(function () {
                self.fetchFolders();
            }).fail(function (xhr) {
                var error = xhr.responseJSON ? xhr.responseJSON.message : 'Error renaming folder.';
                alert(error);
            });
        },

        deleteFolder: function (e) {
            e.preventDefault();
            e.stopPropagation();

            var self = this;
            var $item = $(e.currentTarget).closest('.mm-folder-item');
            var folderId = $item.data('folder-id');

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
                if (self.currentFolder == folderId) {
                    self.currentFolder = '';
                    self.browser.collection.props.set({ mm_folder: '' });
                }
                self.fetchFolders();
            }).fail(function (xhr) {
                var error = xhr.responseJSON ? xhr.responseJSON.message : 'Error deleting folder.';
                alert(error);
            });
        },

        bindDroppables: function () {
            var self = this;
            this.$('.mm-folder-item').droppable({
                accept: '.attachment',
                hoverClass: 'mm-folder-hover',
                tolerance: 'pointer',
                drop: function (event, ui) {
                    var folderId = $(this).data('folder-id');
                    var draggedId = parseInt(ui.helper.data('attachment-id'), 10);
                    var ids = [draggedId];

                    // Check if dragged item is part of a bulk selection
                    var selection = self.controller.state().get('selection');
                    if (selection && selection.length > 0) {
                        var selectionIds = selection.pluck('id');
                        if (_.contains(selectionIds, draggedId)) {
                            ids = selectionIds;
                        }
                    }

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
                        if (response && response.folders) {
                            self.folders = response.folders;
                            self.counts.unassigned = response.unassigned;
                            self.counts.all = response.all;
                            self.render();
                        }
                        
                        // Clear active selections
                        if (selection) {
                            selection.reset();
                        }

                        // Refresh grid view
                        self.browser.collection.fetch({ reset: true });
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

            // Add folders sidebar
            this.foldersSidebar = new media.view.MediaMaestroFolderSidebar({
                browser: this,
                controller: this.controller
            });

            this.views.add(this.foldersSidebar);

            // Listen to attachment load / sync events to attach draggable attributes
            this.listenTo(this.collection, 'sync reset add', this.bindDraggables);
        },

        ready: function () {
            originalAttachmentsBrowser.prototype.ready.apply(this, arguments);

            // Position sidebar in DOM and add offset class to main layout
            this.$el.addClass('has-folders-sidebar');
            this.$el.prepend(this.foldersSidebar.el);
            this.foldersSidebar.render();

            this.bindDraggables();
        },

        bindDraggables: function () {
            var self = this;
            // Delay slightly to ensure elements are rendered in DOM
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
                            ui.helper.data('attachment-id', id);
                            ui.helper.addClass('ui-draggable-dragging');
                        }
                    });
                });
            }, 300);
        }
    });

})(jQuery, _);
