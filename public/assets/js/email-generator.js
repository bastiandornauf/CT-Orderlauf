const DEFAULT_ORDER_SUBJECT = 'Bestellung {{COMPANY}} {{TARGET_DATE}}';

/**
 * @param {{ subjectTemplate?: string, supplierName: string, targetDateFormatted: string, companyName?: string, appName?: string }} p
 */
export function buildMailSubject(p) {
  const tplRaw = p.subjectTemplate != null && String(p.subjectTemplate).trim() !== ''
    ? String(p.subjectTemplate)
    : DEFAULT_ORDER_SUBJECT;
  const company =
    String(p.companyName ?? '').trim() || String(p.appName ?? '').trim() || '';
  const appName = String(p.appName ?? '').trim();
  const supplier = String(p.supplierName ?? '').trim();
  const date = String(p.targetDateFormatted ?? '').trim();
  let s = tplRaw
    .replaceAll('{{COMPANY}}', company)
    .replaceAll('{{APP_NAME}}', appName)
    .replaceAll('{{SUPPLIER}}', supplier)
    .replaceAll('{{TARGET_DATE}}', date)
    .replaceAll('{{DATE_TODAY}}', date)
    .replaceAll('[DATE_TODAY]', date);
  s = s.replace(/\s+/g, ' ').trim();
  return s;
}

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
    subjectTemplate,
    companyName,
    appName,
  } = opts;

  const defaultBody = `Bestellung für {{TARGET_DATE}}\n\n{{LINES}}\n\n{{IF ADDONS}}Zusätzlich:\n{{ADDONS}}\n{{ENDIF}}\n{{SUPPLIER_NOTE}}`;
  let tpl = template && String(template).trim() !== '' ? String(template) : defaultBody;

  let linesBlock = '';
  for (const l of lines) {
    linesBlock += `- ${l.label}: ${l.quantity} ${l.unit}\n`;
  }
  if (!linesBlock) linesBlock = '(keine Artikel)\n';

  let freeBlock = '';
  if (freeLines?.length) {
    for (const f of freeLines) {
      freeBlock += `- ${f}\n`;
    }
  }

  const noteBlock = supplierNote ? `Hinweis:\n${supplierNote}\n` : '';

  let body = tpl
    .replaceAll('{{TARGET_DATE}}', targetDateFormatted)
    .replaceAll('{{DATE_TODAY}}', targetDateFormatted)
    .replaceAll('[DATE_TODAY]', targetDateFormatted)
    .replaceAll('{{SUPPLIER}}', supplierName)
    .replaceAll('{{LINES}}', linesBlock)
    .replaceAll('{{ADDONS}}', freeBlock)
    .replaceAll('{{SUPPLIER_NOTE}}', noteBlock);

  if (body.includes('{{IF ADDONS}}')) {
    const has = freeLines?.length > 0;
    body = body.replace(/\{\{IF ADDONS\}\}[\s\S]*?\{\{ENDIF\}\}/g, has ? '$&' : '');
    body = body.replaceAll('{{IF ADDONS}}', '').replaceAll('{{ENDIF}}', '');
  }

  const subject = buildMailSubject({
    subjectTemplate,
    supplierName,
    targetDateFormatted,
    companyName,
    appName,
  });
  return { subject, body: body.trim() };
}

/**
 * @param {string} to
 * @param {string} subject
 * @param {string} body
 * @param {string} [cc]
 * @param {{devMode?: boolean, devEmail?: string}} [devOpts]
 */
export function mailtoLink(to, subject, body, cc, devOpts) {
  let actualTo = to;
  let actualCc = cc;
  let actualSubject = subject;

  if (devOpts?.devMode && devOpts?.devEmail) {
    actualTo = devOpts.devEmail;
    actualCc = '';
    actualSubject = `[TEST an ${to}] ${subject}`;
  }

  let q = `subject=${encodeURIComponent(actualSubject)}&body=${encodeURIComponent(body)}`;
  if (actualCc) {
    q += `&cc=${encodeURIComponent(actualCc)}`;
  }
  return `mailto:${encodeURIComponent(actualTo)}?${q}`;
}
