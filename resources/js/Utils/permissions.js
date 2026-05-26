export function getPermissionNames(user) {
    const permissions = user?.permissions ?? user?.permission_names ?? [];

    if (!Array.isArray(permissions)) {
        return [];
    }

    return permissions
        .map((permission) => typeof permission === 'string' ? permission : permission?.name)
        .filter(Boolean);
}

export function can(user, permission) {
    if (!permission) {
        return true;
    }

    return getPermissionNames(user).includes(permission);
}

export function canAny(user, permissions = []) {
    if (!permissions.length) {
        return true;
    }

    const userPermissions = new Set(getPermissionNames(user));
    return permissions.some((permission) => userPermissions.has(permission));
}
