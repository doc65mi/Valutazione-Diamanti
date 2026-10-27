"""Immagini Open Graph 1200x630 per le pagine del sito e per il blog (2.1.63).
Uso: python tools/og_images.py <cartella con CormorantGaramond.ttf e Manrope.ttf>
Scrive assets/og/<pagina>.<lingua>.jpg con i colori e il logo del sito."""
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
FONTS = Path(sys.argv[1] if len(sys.argv) > 1 else ROOT / 'tools/fonts')
W, H = 1200, 630
BG, GOLD, CREAM, MUTED = '#1E2A2B', '#C9A96E', '#F2EEE6', '#AEB6B2'
PAGES = {
    'home': ('Vendi il tuo diamante a un compratore diretto', 'Sell your diamond to a direct buyer'),
    'acquistiamo': ('Cosa acquistiamo: diamanti, gioielli e orologi', 'What we buy: diamonds, jewellery and watches'),
    'valutazione': ('Valutazione gratuita del tuo diamante', 'Free valuation of your diamond'),
    'chisiamo': ('Cinque generazioni di diamantaires, a Milano dal 2009', 'Five generations of diamantaires, in Milan since 2009'),
    'faq': ('Domande frequenti sulla vendita di diamanti', 'Questions about selling your diamond'),
    'privacy': ('Informativa privacy', 'Privacy policy'),
    'cookie': ('Informativa cookie', 'Cookie policy'),
    'blog': ('Guide e approfondimenti su diamanti e gioielli', 'Guides and insights on diamonds and jewellery'),
}
KICKER = ('VALUTAZIONE GRATUITA E SENZA IMPEGNO · MILANO', 'FREE, NO-OBLIGATION VALUATION · MILAN')
FOOT = 'Via Vincenzo Monti 47, Milano · +39 02 466 891 · valutazionediamanti.it'

def font(name, size, weight):
    f = ImageFont.truetype(str(FONTS / name), size)
    try: f.set_variation_by_axes([weight])
    except Exception: pass
    return f

def words(draw, xy, text, f, fill, track=0.0):
    """Testo parola per parola: lo spazio del Manrope variabile esce troppo stretto con Pillow."""
    x, y = xy; gap = f.size * 0.32
    for word in text.split(' '):
        for ch in word:
            draw.text((x, y), ch, font=f, fill=fill); x += draw.textlength(ch, font=f) + track
        x += gap

def wrap(draw, text, f, width):
    lines, line = [], ''
    for word in text.split():
        test = (line + ' ' + word).strip()
        if draw.textlength(test, font=f) <= width or not line: line = test
        else: lines.append(line); line = word
    return lines + [line]

def diamond(draw, x, y, s):
    p = lambda pts: [(x + a * s / 64, y + b * s / 64) for a, b in pts]
    w = max(2, round(s / 20))
    draw.line(p([(14, 25), (22, 14), (42, 14), (50, 25), (32, 51), (14, 25)]), fill=GOLD, width=w, joint='curve')
    draw.line(p([(14, 25), (50, 25)]), fill=GOLD, width=w)
    draw.line(p([(22, 14), (27, 25), (32, 14), (37, 25), (42, 14)]), fill=GOLD, width=w, joint='curve')
    draw.line(p([(27, 25), (32, 51), (37, 25)]), fill=GOLD, width=w, joint='curve')

def render(title, kicker, out):
    im = Image.new('RGB', (W, H), BG); d = ImageDraw.Draw(im)
    d.rectangle([28, 28, W - 29, H - 29], outline='#3A4748', width=1)
    diamond(d, 72, 62, 76)
    d.text((164, 74), 'Valutazione Diamanti', font=font('CormorantGaramond.ttf', 40, 600), fill=CREAM)
    words(d, (166, 122), 'BY LA DIAMANTERIA', font('Manrope.ttf', 15, 600), MUTED, 2.2)
    words(d, (80, 214), kicker, font('Manrope.ttf', 18, 700), GOLD, 1.6)
    size = 76
    while True:
        t = font('CormorantGaramond.ttf', size, 500); lines = wrap(d, title, t, W - 170)
        if len(lines) <= 3 or size <= 52: break
        size -= 4
    y = 252
    for line in lines: d.text((78, y), line, font=t, fill=CREAM); y += int(size * 1.08)
    d.line([(80, H - 104), (200, H - 104)], fill=GOLD, width=2)
    words(d, (80, H - 86), FOOT, font('Manrope.ttf', 19, 500), MUTED)
    im.save(out, 'JPEG', quality=86, optimize=True, progressive=True)

for slug, titles in PAGES.items():
    for i, lang in enumerate(('it', 'en')):
        render(titles[i], KICKER[i], ROOT / 'assets/og' / f'{slug}.{lang}.jpg')
print('ok')
