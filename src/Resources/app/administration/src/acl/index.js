Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'scale_video_optimizer',
    roles: {
        viewer: {
            privileges: ['scale_video_optimizer:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['scale_video_optimizer:update'],
            dependencies: ['scale_video_optimizer.viewer'],
        },
        creator: {
            privileges: ['scale_video_optimizer:create'],
            dependencies: ['scale_video_optimizer.viewer', 'scale_video_optimizer.editor'],
        },
        deleter: {
            privileges: ['scale_video_optimizer:delete'],
            dependencies: ['scale_video_optimizer.viewer'],
        },
    },
});
