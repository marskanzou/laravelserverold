// === AVIF SUPPORT IN ADMIN PANEL ===

function imageFormatter(value, row) {
    if (!value) {
        return '<span class="text-muted">No Image</span>';
    }

    var url = '/storage/' + value;
    var isAvif = value.toLowerCase().endsWith('.avif');

    if (isAvif) {
        return `
            <picture>
                <source srcset="${url}" type="image/avif">
                <img src="${url.replace('.avif', '.jpg')}" 
                     alt="Item Image" 
                     class="img-thumbnail" 
                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
            </picture>
        `;
    } else {
        return `<img src="${url}" alt="Item Image" class="img-thumbnail" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">`;
    }
}

function galleryImageFormatter(value, row) {
    if (!value || value.length === 0) {
        return '<span class="text-muted">No Gallery</span>';
    }

    var html = '<div class="d-flex flex-wrap gap-1">';
    value.forEach(function(img) {
        var imgPath = img.image || img;
        var url = '/storage/' + imgPath;
        var isAvif = imgPath.toLowerCase().endsWith('.avif');

        if (isAvif) {
            html += `
                <picture>
                    <source srcset="${url}" type="image/avif">
                    <img src="${url.replace('.avif', '.jpg')}" 
                         class="img-thumbnail" 
                         style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;">
                </picture>
            `;
        } else {
            html += `<img src="${url}" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;">`;
        }
    });
    html += '</div>';
    return html;
}
import './bootstrap';
