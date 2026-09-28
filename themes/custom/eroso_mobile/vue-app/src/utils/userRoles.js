export function getUserRoles() {
  try {
    const roles = JSON.parse(localStorage.getItem('roles') || '[]');
    return Array.isArray(roles) ? roles : [];
  } catch {
    return [];
  }
}

export function hasRole(role) {
  return getUserRoles().includes(role);
}

export function isAdmin() {
  return hasRole('administrator');
}

export function isContentEditor() {
  return hasRole('content_editor');
}

export function canAccessBackOffice() {
  return isAdmin() || isContentEditor();
}
