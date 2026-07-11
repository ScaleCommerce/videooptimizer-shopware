Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'video_optimizer',
    roles: {
        viewer: {
            privileges: ['video_optimizer:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['video_optimizer:update'],
            dependencies: ['video_optimizer.viewer'],
        },
        creator: {
            privileges: ['video_optimizer:create'],
            dependencies: ['video_optimizer.viewer', 'video_optimizer.editor'],
        },
        deleter: {
            privileges: ['video_optimizer:delete'],
            dependencies: ['video_optimizer.viewer'],
        },
    },
});
