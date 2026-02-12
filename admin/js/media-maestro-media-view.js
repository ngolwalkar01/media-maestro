/**
 * Media Maestro - Media View Integration
 * 
 * Extends the WordPress Media Library modal to add AI Tools.
 */
(function ($, _) {
    var media = wp.media;

    // We verified mm_data exists in the previous step, ensuring it's available here too
    if (typeof mm_data === 'undefined') {
        return;
    }

    /**
     * View for the AI Tools Sidebar Section
     */
    media.view.MediaMaestroSidebar = media.view.PriorityList.extend({
        className: 'mm-sidebar-section',
        template: wp.template('mm-sidebar-template'),

        events: {
            'click .mm-btn-remove-bg': 'removeBackground',
            'click .mm-btn-style': 'styleTransfer'
        },

        initialize: function (options) {
            media.view.PriorityList.prototype.initialize.apply(this, arguments);
            this.model.on('change', this.render, this);
        },

        prepare: function () {
            var data = this.model.toJSON();
            // Add any dynamic data needed for the template
            return data;
        },

        render: function () {
            // Basic render
            this.$el.html(this.template(this.prepare()));
            return this;
        },

        removeBackground: function (e) {
            e.preventDefault();
            this.startJob('remove_background');
        },

        styleTransfer: function (e) {
            e.preventDefault();
            this.startJob('style_transfer', { prompt: 'Oil painting' });
        },

        startJob: function (operation, params = {}) {
            var self = this;
            var attachmentId = this.model.get('id');
            var $status = this.$('.mm-status');

            $status.text('Starting ' + operation + '...');

            $.ajax({
                url: mm_data.api_url,
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', mm_data.nonce);
                },
                data: {
                    attachment_id: attachmentId,
                    operation: operation,
                    params: params
                }
            }).done(function (response) {
                if (response.id) {
                    $status.text('Job ' + response.id + ' started...');
                    self.pollJob(response.id);
                } else {
                    $status.text('Failed to start.');
                }
            }).fail(function () {
                $status.text('Request failed.');
            });
        },

        pollJob: function (jobId) {
            var self = this;
            var $status = this.$('.mm-status');

            setTimeout(function () {
                $.ajax({
                    url: mm_data.api_url + '/' + jobId,
                    method: 'GET',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', mm_data.nonce);
                    }
                }).done(function (response) {
                    if (response.status === 'completed') {
                        $status.html('Done! <br>New image created.');
                        // Refresh the library?
                        // self.model.collection.props.set({ ignore: (+ new Date()) }); // force refresh trick?
                    } else if (response.status === 'failed') {
                        $status.text('Failed: ' + response.error);
                    } else {
                        $status.text(response.status + '...');
                        self.pollJob(jobId);
                    }
                });
            }, 2000);
        }
    });

    /**
     * Extend the Attachment Details hook to inject our sidebar
     */
    var originalAttachmentDetails = media.view.Attachment.Details;
    media.view.Attachment.Details = originalAttachmentDetails.extend({
        initialize: function () {
            originalAttachmentDetails.prototype.initialize.apply(this, arguments);

            // Create our sidebar view
            this.on('ready', this.renderMediaMaestro, this);
        },

        renderMediaMaestro: function () {
            // Check if it's an image
            if (this.model.get('type') !== 'image') {
                return;
            }

            // We want to append to the sidebar details
            // The template usually has a .details or .settings area
            // Ideally we hook into a specific region if possible, 
            // but for now let's just append to the $el

            var sidebarView = new media.view.MediaMaestroSidebar({
                controller: this.controller,
                model: this.model,
                priority: 200
            });

            this.views.add('.compat-attachment-fields', sidebarView);
            // .compat-attachment-fields is usually where extra fields go. 
            // Or we can try appending to .details directly
        }
    });

})(jQuery, _);
