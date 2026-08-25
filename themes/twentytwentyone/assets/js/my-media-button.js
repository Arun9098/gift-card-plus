(function() {
    tinymce.create('tinymce.plugins.my_media_button', {
        init: function(editor, url) {
            editor.addButton('my_media_button', {
                text: '',
                icon: 'image',
                onclick: function() {
                    wp.media.editor.open(editor.id);
                }
            });
        }
    });
    tinymce.PluginManager.add('my_media_button', tinymce.plugins.my_media_button);
})();
