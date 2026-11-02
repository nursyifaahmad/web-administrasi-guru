import './bootstrap';
import Alpine from 'alpinejs';

window.initSignatureCanvas = function (canvas, onUpdateCallback) {
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let hasDrawn = false;

    ctx.strokeStyle = '#0f172a';
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    const getPos = (e) => {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / (rect.width || 1);
        const scaleY = canvas.height / (rect.height || 1);
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY,
        };
    };

    const start = (e) => {
        e.preventDefault();
        drawing = true;
        hasDrawn = true;
        const p = getPos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    };

    const move = (e) => {
        if (!drawing) return;
        e.preventDefault();
        const p = getPos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    };

    const end = () => {
        if (!drawing) return;
        drawing = false;
        if (hasDrawn && typeof onUpdateCallback === 'function') {
            onUpdateCallback(canvas.toDataURL('image/png'));
        }
    };

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);

    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    window.addEventListener('touchend', end);

    return {
        clear() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
            if (typeof onUpdateCallback === 'function') {
                onUpdateCallback('');
            }
        },
        toDataURL() {
            return hasDrawn ? canvas.toDataURL('image/png') : '';
        },
    };
};

window.Alpine = Alpine;
Alpine.start();
