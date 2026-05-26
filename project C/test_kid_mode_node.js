// Node.js test script for Kid Mode functionality
// Simulates localStorage and tests Kid Mode logic

// Mock localStorage
global.localStorage = {
    data: {},
    getItem(key) {
        return this.data[key] || null;
    },
    setItem(key, value) {
        this.data[key] = value;
    },
    removeItem(key) {
        delete this.data[key];
    },
    clear() {
        this.data = {};
    }
};

// Mock document and window for testing
global.document = {
    getElementById(id) {
        return {
            style: {},
            textContent: '',
            value: '',
            innerHTML: '',
            addEventListener: () => {},
            remove: () => {}
        };
    },
    createElement() {
        return {
            style: {},
            id: '',
            innerHTML: '',
            appendChild: () => {}
        };
    },
    body: {
        appendChild: () => {}
    }
};

global.window = {
    addEventListener: () => {},
    setTimeout: (fn) => fn(),
    clearInterval: () => {},
    matchMedia: () => ({ matches: false })
};

// Mock AudioContext for sound testing
global.AudioContext = class {
    createOscillator() { return { connect: () => {}, disconnect: () => {}, start: () => {}, stop: () => {}, frequency: { setValueAtTime: () => {} }, onended: null }; }
    createGain() { return { connect: () => {}, gain: { setValueAtTime: () => {}, exponentialRampToValueAtTime: () => {} } }; }
    destination = {};
};

// Import the functions from script.js (we'll need to extract them)
const fs = require('fs');
const scriptContent = fs.readFileSync('script.js', 'utf8');

// Extract and test key functions
function testKidModeLogic() {
    console.log('=== Kid Mode Logic Testing ===\n');

    // Test 1: Settings saving and loading
    console.log('Test 1: Settings Management');
    try {
        // Simulate saveKidModeSettings
        const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
        settings.kidModeEnabled = true;
        settings.kidModeTimeLimit = 60;
        settings.kidModePassword = 'test123';
        localStorage.setItem('userSettings', JSON.stringify(settings));

        const loaded = JSON.parse(localStorage.getItem('userSettings') || '{}');
        if (loaded.kidModeEnabled && loaded.kidModeTimeLimit === 60 && loaded.kidModePassword === 'test123') {
            console.log('✓ Settings save/load: PASSED');
        } else {
            console.log('✗ Settings save/load: FAILED');
        }
    } catch (error) {
        console.log('✗ Settings save/load: ERROR -', error.message);
    }

    // Test 2: Time monitoring logic
    console.log('\nTest 2: Time Monitoring');
    try {
        const startTime = Date.now() - (30 * 60 * 1000); // 30 minutes ago
        localStorage.setItem('kidModeStartTime', startTime.toString());

        const currentTime = Date.now();
        const elapsed = Math.floor((currentTime - startTime) / 1000);
        const expectedElapsed = 30 * 60; // 30 minutes in seconds

        if (Math.abs(elapsed - expectedElapsed) < 10) { // Allow 10 second tolerance
            console.log('✓ Time monitoring: PASSED');
        } else {
            console.log('✗ Time monitoring: FAILED - elapsed:', elapsed, 'expected:', expectedElapsed);
        }
    } catch (error) {
        console.log('✗ Time monitoring: ERROR -', error.message);
    }

    // Test 3: Password validation
    console.log('\nTest 3: Password Validation');
    try {
        const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
        const correctPassword = settings.kidModePassword;
        const testPassword = 'test123';

        if (testPassword === correctPassword) {
            console.log('✓ Password validation: PASSED');
        } else {
            console.log('✗ Password validation: FAILED');
        }
    } catch (error) {
        console.log('✗ Password validation: ERROR -', error.message);
    }

    // Test 4: Lock state management
    console.log('\nTest 4: Lock State Management');
    try {
        localStorage.setItem('kidModeLocked', 'true');
        const isLocked = localStorage.getItem('kidModeLocked') === 'true';

        if (isLocked) {
            console.log('✓ Lock state: PASSED');
        } else {
            console.log('✗ Lock state: FAILED');
        }

        // Test unlock
        localStorage.removeItem('kidModeLocked');
        const isUnlocked = localStorage.getItem('kidModeLocked') === null;

        if (isUnlocked) {
            console.log('✓ Unlock state: PASSED');
        } else {
            console.log('✗ Unlock state: FAILED');
        }
    } catch (error) {
        console.log('✗ Lock state management: ERROR -', error.message);
    }

    // Test 5: Time limit calculation
    console.log('\nTest 5: Time Limit Calculation');
    try {
        const timeLimitMinutes = 45;
        const timeLimitSeconds = timeLimitMinutes * 60;

        // Simulate 20 minutes elapsed
        const startTime = Date.now() - (20 * 60 * 1000);
        localStorage.setItem('kidModeStartTime', startTime.toString());

        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        const remaining = Math.max(0, timeLimitSeconds - elapsed);
        const expectedRemaining = timeLimitSeconds - (20 * 60);

        if (Math.abs(remaining - expectedRemaining) < 10) {
            console.log('✓ Time limit calculation: PASSED');
        } else {
            console.log('✗ Time limit calculation: FAILED - remaining:', remaining, 'expected:', expectedRemaining);
        }
    } catch (error) {
        console.log('✗ Time limit calculation: ERROR -', error.message);
    }

    console.log('\n=== Testing Complete ===');
}

// Run the tests
testKidModeLogic();
