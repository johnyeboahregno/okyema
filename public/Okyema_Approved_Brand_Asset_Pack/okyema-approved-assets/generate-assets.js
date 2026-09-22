const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const root = __dirname;
const sizes = [16, 32, 48, 64, 72, 96, 128, 144, 152, 180, 192, 256, 384, 512, 1024];
const mark = fs.readFileSync(path.join(root, 'okyema-mark.svg'), 'utf8');

function svgFile(name, body) { fs.writeFileSync(path.join(root, name), body); }
function markAt(x, y, size) {
  return `<svg x="${x}" y="${y}" width="${size}" height="${size}" viewBox="0 0 512 512">${mark.replace(/^.*?<svg[^>]*>/s,'').replace(/<\/svg>\s*$/,'')}</svg>`;
}
async function png(source, target, width, height) {
  await sharp(path.join(root, source), {density: 384}).resize(width, height, {fit:'fill'}).png().toFile(path.join(root, target));
}

async function main() {
  ['app-icons','favicon','logos','backgrounds','previews'].forEach(d => fs.mkdirSync(path.join(root,d), {recursive:true}));
  const dark = '#0B1020', light = '#F5F7FB';
  const icon = bg => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><rect width="1024" height="1024" rx="224" fill="${bg}"/>${markAt(112,112,800)}</svg>`;
  svgFile('app-icon-dark.svg', icon(dark));
  svgFile('app-icon-light.svg', icon(light));
  svgFile('okyema-mark-dark.svg', mark);
  svgFile('okyema-mark-light.svg', mark);
  const logo = color => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 280">${markAt(20,20,240)}<text x="305" y="175" fill="${color}" font-family="Inter,Arial,sans-serif" font-size="118" font-weight="500" letter-spacing="28">OKYEMA</text><text x="310" y="226" fill="${color}" opacity=".68" font-family="Inter,Arial,sans-serif" font-size="26" font-weight="400" letter-spacing="4">YOUR INTELLIGENT CHIEF OF STAFF</text></svg>`;
  svgFile('logos/okyema-logo-on-dark.svg', logo('#FFFFFF'));
  svgFile('logos/okyema-logo-on-light.svg', logo(dark));
  svgFile('favicon/favicon.svg', mark);
  const background = (mode, w, h) => {
    const isDark = mode === 'dark';
    const base = isDark ? '#0B1020' : '#F5F7FB';
    const haze = isDark ? '#151D3B' : '#FFFFFF';
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}"><defs><radialGradient id="a" cx="82%" cy="8%" r="82%"><stop stop-color="#7457FF" stop-opacity="${isDark?'.24':'.12'}"/><stop offset=".48" stop-color="#347DF2" stop-opacity="${isDark?'.10':'.07'}"/><stop offset="1" stop-color="${base}" stop-opacity="0"/></radialGradient><radialGradient id="b" cx="2%" cy="100%" r="62%"><stop stop-color="#36D7EB" stop-opacity="${isDark?'.10':'.14'}"/><stop offset="1" stop-color="${haze}" stop-opacity="0"/></radialGradient></defs><rect width="${w}" height="${h}" fill="${base}"/><rect width="${w}" height="${h}" fill="url(#a)"/><rect width="${w}" height="${h}" fill="url(#b)"/></svg>`;
  };
  for (const mode of ['dark','light']) {
    svgFile(`backgrounds/background-${mode}.svg`, background(mode,1920,1080));
    for (const [name,w,h] of [['desktop',1920,1080],['tablet',1366,1024],['mobile',1080,1920]]) {
      const file = `backgrounds/${name}-${mode}-${w}x${h}.svg`;
      svgFile(file, background(mode,w,h));
      await png(file, file.replace('.svg','.png'), w, h);
    }
  }
  const fg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1080">${markAt(190,190,700)}</svg>`;
  svgFile('app-icons/android-adaptive-foreground.svg', fg);
  svgFile('app-icons/android-adaptive-background-dark.svg', `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1080"><rect width="1080" height="1080" fill="${dark}"/></svg>`);
  svgFile('app-icons/android-adaptive-background-light.svg', `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1080"><rect width="1080" height="1080" fill="${light}"/></svg>`);
  for (const mode of ['dark','light']) for (const size of sizes) await png(`app-icon-${mode}.svg`, `app-icons/okyema-${mode}-${size}.png`, size, size);
  for (const size of [16,32,48]) await png('favicon/favicon.svg', `favicon/favicon-${size}.png`, size, size);
  await png('app-icon-dark.svg','previews/app-icon-dark.png',640,640);
  await png('app-icon-light.svg','previews/app-icon-light.png',640,640);
  await png('logos/okyema-logo-on-dark.svg','previews/logo-on-dark.png',1200,280);
  await png('logos/okyema-logo-on-light.svg','previews/logo-on-light.png',1200,280);
  await sharp(path.join(root,'favicon/favicon-16.png')).toFile(path.join(root,'favicon/favicon.ico'));
}
main().catch(e => { console.error(e); process.exit(1); });
