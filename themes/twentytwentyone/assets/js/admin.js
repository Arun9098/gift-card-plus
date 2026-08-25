// Admin JS for image upload
jQuery(document).ready(function($) {
    console.log('loaded.....');
    $('.upload-image-button').click(function(e) {
        console.log('jiiiiiiiii');
        e.preventDefault();
        
        var button = $(this);
        var targetInput = button.parent().find('input[type="hidden"]');
        var preview = button.parent().find('.image-preview');
        var removeButton = button.parent().find('.remove-image-button');

        var frame = wp.media({
            title: 'Select Image',
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            targetInput.val(attachment.id);
            preview.attr('src', attachment.url).show();
            removeButton.show();
        });

        frame.open();
    });

    $('.remove-image-button').click(function(e) {
        e.preventDefault();
        var container = $(this).parent();
        container.find('input[type="hidden"]').val('');
        container.find('.image-preview').hide().attr('src', '');
        $(this).hide();
    });
});