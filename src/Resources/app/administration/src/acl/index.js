Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'scalecommerce_vo',
    roles: {
        viewer: {
            privileges: ['scalecommerce_vo:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['scalecommerce_vo:update'],
            dependencies: ['scalecommerce_vo.viewer'],
        },
        creator: {
            privileges: ['scalecommerce_vo:create'],
            dependencies: ['scalecommerce_vo.viewer', 'scalecommerce_vo.editor'],
        },
        deleter: {
            privileges: ['scalecommerce_vo:delete'],
            dependencies: ['scalecommerce_vo.viewer'],
        },
    },
});
