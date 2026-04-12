/**
 * Client-side mail body preview (mirrors PHP EmailService placeholders).
 * @param {object} opts
 */
export function buildMailPreview(opts) {
  const {
    template,
    supplierName,
    targetDateFormatted,
    lines,
    freeLines,
    supplierNote,
  } = opts;

  const defaultBody = `Bestellung für {{TARGET_DATE}}\n\n{{LINES}}\n\n{{FREE_ITEMS}}\n\n{{SUPPLIER_NOTE}}`;
  let tpl = template && String(template).trim() !== '' ? String(template) : defaultBody;

  let linesBlock = '';
  for (const l of lines) {
    linesBlock += `- ${l.label}: ${l.quantity} ${l.unit}\n`;
  }
  if (!linesBlock) linesBlock = '(keine Artikel)\n';

  let freeBlock = '';
  if (freeLines?.length) {
    freeBlock = 'Zusätzlich:\n';
    for (const f of freeLines) {
      freeBlock += `- ${f}\n`;
    }
  }

  const noteBlock = supplierNote ? `Hinweis:\n${supplierNote}\n` : '';

  let body = tpl
    .replaceAll('{{TARGET_DATE}}', targetDateFormatted)
    .replaceAll('{{SUPPLIER}}', supplierName)
    .replaceAll('{{LINES}}', linesBlock)
    .replaceAll('{{FREE_ITEMS}}', freeBlock)
    .replaceAll('{{SUPPLIER_NOTE}}', noteBlock)
    .replaceAll('[DATE_TODAY]', targetDateFormatted);

  if (body.includes('[IF ADDONS]')) {
    const has = freeLines?.length > 0;
    body = body.replace(/\[IF ADDONS\][\s\S]*?\[ENDIF\]/g, has ? '$&' : '');
    body = body.replaceAll('[IF ADDONS]', '').replaceAll('[ENDIF]', '');
  }

  const subject = `Bestellung ${supplierName} ${targetDateFormatted}`;
  return { subject, body: body.trim() };
}

export function mailtoLink(to, subject, body, cc) {
  let q = `subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  if (cc) {
    q += `&cc=${encodeURIComponent(cc)}`;
  }
  return `mailto:${encodeURIComponent(to)}?${q}`;
}
