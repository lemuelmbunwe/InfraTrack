const homeRouteNames = {
  admin: 'admin-home',
  inspector: 'inspector-home',
  contractor: 'contractor-home',
}

export function homeRouteName(role) {
  return homeRouteNames[role] || 'login'
}