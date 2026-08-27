import { notarize } from '@electron/notarize';

export default async (context) => {
    if (process.platform !== 'darwin') {
        return;
    }

    if (context.packager.platform.name !== 'mac') {
        return;
    }

    const enabled = process.env.NATIVEPHP_NOTARIZE === '1'
        || process.env.NATIVEPHP_NOTARIZE === 'true';

    const hasCredentials = Boolean(
        process.env.NATIVEPHP_APPLE_ID
        && process.env.NATIVEPHP_APPLE_ID_PASS
        && process.env.NATIVEPHP_APPLE_TEAM_ID,
    );

    if (! enabled || ! hasCredentials) {
        console.log('  • skipping notarization (set NATIVEPHP_NOTARIZE=1 and Apple credentials to enable)');

        return;
    }

    console.log('aftersign hook triggered, start to notarize app.');

    const appId = process.env.NATIVEPHP_APP_ID;
    const { appOutDir } = context;
    const appName = context.packager.appInfo.productFilename;

    try {
        await notarize({
            appBundleId: appId,
            appPath: `${appOutDir}/${appName}.app`,
            appleId: process.env.NATIVEPHP_APPLE_ID,
            appleIdPassword: process.env.NATIVEPHP_APPLE_ID_PASS,
            teamId: process.env.NATIVEPHP_APPLE_TEAM_ID,
            tool: 'notarytool',
        });
    } catch (error) {
        console.error(error);
    }

    console.log(`done notarizing ${appId}.`);
};
