import { app } from 'electron'
import { existsSync, statSync } from 'fs'
import { join } from 'path'

/**
 * The collector was rebranded from Biometric Collector to Pulse.
 * Keep SQLite in the original Biometric Collector userData folder so:
 * 1. Existing campus DBs survive the rename
 * 2. This app does not share Electron userData with People360 (which also
 *    used to be named Pulse)
 */
function hasExistingSqlite(userDataDir) {
    const file = join(userDataDir, 'storage', 'app', 'biometric-collector.sqlite')

    try {
        return existsSync(file) && statSync(file).size > 0
    } catch {
        return false
    }
}

const appData = app.getPath('appData')
const legacyNames = ['Biometric Collector', 'biometric-collector']

for (const folderName of legacyNames) {
    const legacyDir = join(appData, folderName)

    if (hasExistingSqlite(legacyDir)) {
        app.setPath('userData', legacyDir)
        break
    }
}

if (!legacyNames.some((name) => app.getPath('userData') === join(appData, name))) {
    app.setPath('userData', join(appData, 'Biometric Collector'))
}
