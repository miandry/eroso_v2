/**
 * Normalize Drupal link field values from api_solutions JSON (string | { uri } | [{ uri }]).
 */
export function getLinkFieldUri(field) {
  if (field == null || field === '') return '';
  if (typeof field === 'string') return field;
  if (field.uri) return field.uri;
  const first = Array.isArray(field) ? field[0] : field;
  if (first && typeof first === 'object' && first.uri) return first.uri;
  return '';
}
