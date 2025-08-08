jQuery(document).ready(function($) {
    'use strict';

    // Copy link functionality
    $('.copy-link-btn').on('click', function(e) {
        e.preventDefault();
        const url = $(this).data('url');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                alert('Link copied to clipboard!');
            }).catch(function() {
                // Fallback
                copyToClipboardFallback(url);
            });
        } else {
            copyToClipboardFallback(url);
        }
    });

    // Fallback copy method
    function copyToClipboardFallback(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('Link copied to clipboard!');
        } catch (err) {
            console.error('Could not copy text: ', err);
            alert('Could not copy link. Please copy manually: ' + text);
        }
        
        document.body.removeChild(textArea);
    }

    // Test link functionality
    $('.test-link-btn').on('click', function(e) {
        e.preventDefault();
        const url = $(this).data('url');
        window.open(url, '_blank');
    });

    // Delete confirmation
    $('.delete-link-btn').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this link? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
});