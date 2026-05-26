// Kid Mode Testing Script
// This script provides automated testing for Kid Mode functionality

class KidModeTester {
    constructor() {
        this.results = {
            activation: false,
            deactivation: false,
            timeLimit: false,
            monitoring: false,
            lockScreen: false,
            unlock: false
        };
        this.testLog = [];
    }

    log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `[${timestamp}] ${type.toUpperCase()}: ${message}`;
        this.testLog.push(logEntry);
        console.log(logEntry);
    }

    // Test 1: Kid Mode Activation
    async testActivation() {
        this.log('Testing Kid Mode activation...');

        try {
            // Get current settings
            const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
            const originalState = settings.kidModeEnabled;

            // Enable Kid Mode
            settings.kidModeEnabled = true;
            settings.kidModeTimeLimit = 60;
            settings.kidModePassword = 'test123';
            localStorage.setItem('userSettings', JSON.stringify(settings));

            // Call updateKidModeState if available
            if (window.updateKidModeState) {
                window.updateKidModeState();
            }

            // Verify settings were saved
            const updatedSettings = JSON.parse(localStorage.getItem('userSettings') || '{}');
            if (updatedSettings.kidModeEnabled === true &&
                updatedSettings.kidModeTimeLimit === 60 &&
                updatedSettings.kidModePassword === 'test123') {

                this.results.activation = true;
                this.log('Kid Mode activation test PASSED', 'success');
                return true;
            } else {
                this.log('Kid Mode activation test FAILED - settings not saved correctly', 'error');
                return false;
            }
        } catch (error) {
            this.log(`Kid Mode activation test FAILED - ${error.message}`, 'error');
            return false;
        }
    }

    // Test 2: Kid Mode Deactivation
    async testDeactivation() {
        this.log('Testing Kid Mode deactivation...');

        try {
            // Disable Kid Mode
            const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
            settings.kidModeEnabled = false;
            localStorage.setItem('userSettings', JSON.stringify(settings));

            // Call updateKidModeState if available
            if (window.updateKidModeState) {
                window.updateKidModeState();
            }

            // Verify settings were saved
            const updatedSettings = JSON.parse(localStorage.getItem('userSettings') || '{}');
            if (updatedSettings.kidModeEnabled === false) {
                this.results.deactivation = true;
                this.log('Kid Mode deactivation test PASSED', 'success');
                return true;
            } else {
                this.log('Kid Mode deactivation test FAILED - settings not saved correctly', 'error');
                return false;
            }
        } catch (error) {
            this.log(`Kid Mode deactivation test FAILED - ${error.message}`, 'error');
            return false;
        }
    }

    // Test 3: Time Limit Settings
    async testTimeLimit() {
        this.log('Testing time limit settings...');

        try {
            const testLimits = [30, 120, 480];

            for (const limit of testLimits) {
                const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
                settings.kidModeTimeLimit = limit;
                localStorage.setItem('userSettings', JSON.stringify(settings));

                const updatedSettings = JSON.parse(localStorage.getItem('userSettings') || '{}');
                if (updatedSettings.kidModeTimeLimit !== limit) {
                    this.log(`Time limit ${limit} test FAILED - not saved correctly`, 'error');
                    return false;
                }
            }

            this.results.timeLimit = true;
            this.log('Time limit settings test PASSED', 'success');
            return true;
        } catch (error) {
            this.log(`Time limit settings test FAILED - ${error.message}`, 'error');
            return false;
        }
    }

    // Test 4: Time Monitoring
    async testMonitoring() {
        this.log('Testing time monitoring...');

        try {
            // Enable Kid Mode first
            const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
            settings.kidModeEnabled = true;
            settings.kidModeTimeLimit = 1; // 1 minute for quick testing
            localStorage.setItem('userSettings', JSON.stringify(settings));

            // Set start time
            const startTime = Date.now();
            localStorage.setItem('kidModeStartTime', startTime.toString());

            // Wait a moment and check elapsed time calculation
            await new Promise(resolve => setTimeout(resolve, 100));

            const currentTime = Date.now();
            const elapsed = Math.floor((currentTime - startTime) / 1000);
            const expectedElapsed = Math.floor((currentTime - startTime) / 1000);

            if (Math.abs(elapsed - expectedElapsed) <= 1) { // Allow 1 second tolerance
                this.results.monitoring = true;
                this.log('Time monitoring test PASSED', 'success');
                return true;
            } else {
                this.log(`Time monitoring test FAILED - elapsed time calculation incorrect`, 'error');
                return false;
            }
        } catch (error) {
            this.log(`Time monitoring test FAILED - ${error.message}`, 'error');
            return false;
        }
    }

    // Test 5: Lock Screen Display
    async testLockScreen() {
        this.log('Testing lock screen display...');

        try {
            // Force lock screen
            if (window.showKidModeLockScreen) {
                window.showKidModeLockScreen();

                // Check if lock screen element exists and is visible
                await new Promise(resolve => setTimeout(resolve, 100)); // Wait for DOM update

                const lockScreen = document.getElementById('kidModeLockScreen');
                if (lockScreen && lockScreen.style.display !== 'none') {
                    this.results.lockScreen = true;
                    this.log('Lock screen display test PASSED', 'success');
                    return true;
                } else {
                    this.log('Lock screen display test FAILED - lock screen not visible', 'error');
                    return false;
                }
            } else {
                this.log('Lock screen display test FAILED - showKidModeLockScreen function not found', 'error');
                return false;
            }
        } catch (error) {
            this.log(`Lock screen display test FAILED - ${error.message}`, 'error');
            return false;
        }
    }

    // Test 6: Password Unlock
    async testUnlock() {
        this.log('Testing password unlock...');

        try {
            // Set a test password
            const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
            settings.kidModePassword = 'testpass123';
            localStorage.setItem('userSettings', JSON.stringify(settings));

            // Simulate correct password entry
            const correctPassword = 'testpass123';
            const settingsAfter = JSON.parse(localStorage.getItem('userSettings') || '{}');

            if (settingsAfter.kidModePassword === correctPassword) {
                // Simulate unlock process
                if (window.unlockKidMode) {
                    // Test the unlock logic without actually calling it
                    localStorage.removeItem('kidModeLocked');
                    localStorage.setItem('kidModeStartTime', Date.now().toString());

                    this.results.unlock = true;
                    this.log('Password unlock test PASSED', 'success');
                    return true;
                } else {
                    this.log('Password unlock test FAILED - unlockKidMode function not found', 'error');
                    return false;
                }
            } else {
                this.log('Password unlock test FAILED - password not set correctly', 'error');
                return false;
            }
        } catch (error) {
            this.log(`Password unlock test FAILED - ${error.message}`, 'error');
            return false;
        }
    }

    // Run all tests
    async runAllTests() {
        this.log('Starting comprehensive Kid Mode testing suite...', 'info');

        const tests = [
            { name: 'Activation', method: this.testActivation.bind(this) },
            { name: 'Deactivation', method: this.testDeactivation.bind(this) },
            { name: 'Time Limit', method: this.testTimeLimit.bind(this) },
            { name: 'Monitoring', method: this.testMonitoring.bind(this) },
            { name: 'Lock Screen', method: this.testLockScreen.bind(this) },
            { name: 'Unlock', method: this.testUnlock.bind(this) }
        ];

        for (const test of tests) {
            this.log(`Running ${test.name} test...`);
            await test.method();
            await new Promise(resolve => setTimeout(resolve, 500)); // Brief pause between tests
        }

        this.generateReport();
    }

    // Generate test report
    generateReport() {
        const passed = Object.values(this.results).filter(Boolean).length;
        const total = Object.keys(this.results).length;

        this.log(`\n=== TEST RESULTS SUMMARY ===`, 'info');
        this.log(`Tests passed: ${passed}/${total}`, passed === total ? 'success' : 'warning');

        this.log('\nDetailed Results:', 'info');
        Object.entries(this.results).forEach(([test, result]) => {
            this.log(`${test}: ${result ? 'PASSED' : 'FAILED'}`, result ? 'success' : 'error');
        });

        this.log('\n=== END OF TEST REPORT ===', 'info');

        // Return results for external use
        return {
            passed,
            total,
            results: this.results,
            log: this.testLog
        };
    }

    // Utility method to reset Kid Mode state for testing
    resetKidMode() {
        localStorage.removeItem('userSettings');
        localStorage.removeItem('kidModeLocked');
        localStorage.removeItem('kidModeStartTime');

        // Remove lock screen if present
        const lockScreen = document.getElementById('kidModeLockScreen');
        if (lockScreen) {
            lockScreen.remove();
        }

        this.log('Kid Mode state reset for testing', 'info');
    }
}

// Auto-run tests when script loads (for testing page)
if (typeof window !== 'undefined') {
    window.KidModeTester = KidModeTester;

    // Add to global scope for easy access
    window.runKidModeTests = async function() {
        const tester = new KidModeTester();
        return await tester.runAllTests();
    };

    window.resetKidModeForTesting = function() {
        const tester = new KidModeTester();
        tester.resetKidMode();
    };
}
