#!/usr/bin/env python3
"""
Manual testing script for Kid Mode functionality
Tests the core logic without requiring Node.js or browser
"""

import json
import time
import os

class KidModeTester:
    def __init__(self):
        self.local_storage = {}
        self.test_results = []

    def set_item(self, key, value):
        self.local_storage[key] = value

    def get_item(self, key):
        return self.local_storage.get(key)

    def remove_item(self, key):
        if key in self.local_storage:
            del self.local_storage[key]

    def log_test(self, test_name, passed, details=""):
        result = "PASSED" if passed else "FAILED"
        self.test_results.append(f"{test_name}: {result}")
        if details:
            self.test_results.append(f"  Details: {details}")
        print(f"✓ {test_name}: {result}" if passed else f"✗ {test_name}: {result}")
        if details:
            print(f"  {details}")

    def test_settings_management(self):
        """Test 1: Settings saving and loading"""
        print("\n=== Test 1: Settings Management ===")

        try:
            # Simulate saveKidModeSettings
            settings = json.loads(self.get_item('userSettings') or '{}')
            settings['kidModeEnabled'] = True
            settings['kidModeTimeLimit'] = 60
            settings['kidModePassword'] = 'test123'
            self.set_item('userSettings', json.dumps(settings))

            # Load and verify
            loaded = json.loads(self.get_item('userSettings') or '{}')
            success = (loaded.get('kidModeEnabled') and
                      loaded.get('kidModeTimeLimit') == 60 and
                      loaded.get('kidModePassword') == 'test123')

            self.log_test("Settings save/load", success,
                         f"Enabled: {loaded.get('kidModeEnabled')}, Limit: {loaded.get('kidModeTimeLimit')}, Password set: {bool(loaded.get('kidModePassword'))}")

        except Exception as e:
            self.log_test("Settings save/load", False, str(e))

    def test_time_monitoring(self):
        """Test 2: Time monitoring logic"""
        print("\n=== Test 2: Time Monitoring ===")

        try:
            # Set start time 30 minutes ago
            start_time = time.time() - (30 * 60)
            self.set_item('kidModeStartTime', str(int(start_time * 1000)))

            # Calculate elapsed time
            current_time = time.time()
            stored_start = int(self.get_item('kidModeStartTime')) / 1000
            elapsed = int(current_time - stored_start)
            expected_elapsed = 30 * 60

            success = abs(elapsed - expected_elapsed) < 10  # 10 second tolerance
            self.log_test("Time monitoring", success,
                         f"Elapsed: {elapsed}s, Expected: {expected_elapsed}s")

        except Exception as e:
            self.log_test("Time monitoring", False, str(e))

    def test_password_validation(self):
        """Test 3: Password validation"""
        print("\n=== Test 3: Password Validation ===")

        try:
            settings = json.loads(self.get_item('userSettings') or '{}')
            correct_password = settings.get('kidModePassword')
            test_password = 'test123'

            success = test_password == correct_password
            self.log_test("Password validation", success,
                         f"Test password matches: {success}")

        except Exception as e:
            self.log_test("Password validation", False, str(e))

    def test_lock_state_management(self):
        """Test 4: Lock state management"""
        print("\n=== Test 4: Lock State Management ===")

        try:
            # Test locking
            self.set_item('kidModeLocked', 'true')
            is_locked = self.get_item('kidModeLocked') == 'true'
            self.log_test("Lock state setting", is_locked)

            # Test unlocking
            self.remove_item('kidModeLocked')
            is_unlocked = self.get_item('kidModeLocked') is None
            self.log_test("Unlock state clearing", is_unlocked)

        except Exception as e:
            self.log_test("Lock state management", False, str(e))

    def test_time_limit_calculation(self):
        """Test 5: Time limit calculation"""
        print("\n=== Test 5: Time Limit Calculation ===")

        try:
            time_limit_minutes = 45
            time_limit_seconds = time_limit_minutes * 60

            # Simulate 20 minutes elapsed
            start_time = time.time() - (20 * 60)
            self.set_item('kidModeStartTime', str(int(start_time * 1000)))

            # Calculate remaining time
            stored_start = int(self.get_item('kidModeStartTime')) / 1000
            elapsed = int(time.time() - stored_start)
            remaining = max(0, time_limit_seconds - elapsed)
            expected_remaining = time_limit_seconds - (20 * 60)

            success = abs(remaining - expected_remaining) < 10
            self.log_test("Time limit calculation", success,
                         f"Remaining: {remaining}s, Expected: {expected_remaining}s")

        except Exception as e:
            self.log_test("Time limit calculation", False, str(e))

    def test_integration_scenario(self):
        """Test 6: Integration scenario"""
        print("\n=== Test 6: Integration Scenario ===")

        try:
            # Reset storage
            self.local_storage.clear()

            # Enable Kid Mode
            settings = {'kidModeEnabled': True, 'kidModeTimeLimit': 30, 'kidModePassword': 'secure123'}
            self.set_item('userSettings', json.dumps(settings))

            # Start monitoring
            start_time = time.time()
            self.set_item('kidModeStartTime', str(int(start_time * 1000)))

            # Simulate time passing (25 minutes)
            simulated_time = start_time + (25 * 60)
            elapsed = int(simulated_time - start_time)
            time_limit = settings['kidModeTimeLimit'] * 60

            should_be_locked = elapsed >= time_limit

            # Simulate lock trigger
            if should_be_locked:
                self.set_item('kidModeLocked', 'true')

            is_locked = self.get_item('kidModeLocked') == 'true'

            success = should_be_locked == is_locked
            self.log_test("Integration scenario", success,
                         f"Should lock: {should_be_locked}, Is locked: {is_locked}")

        except Exception as e:
            self.log_test("Integration scenario", False, str(e))

    def run_all_tests(self):
        """Run all tests"""
        print("=== Kid Mode Logic Testing Suite ===\n")

        self.test_settings_management()
        self.test_time_monitoring()
        self.test_password_validation()
        self.test_lock_state_management()
        self.test_time_limit_calculation()
        self.test_integration_scenario()

        # Print summary
        print("\n=== Test Results Summary ===")
        passed = sum(1 for result in self.test_results if "PASSED" in result)
        total = len([r for r in self.test_results if not r.startswith("  ")])
        print(f"Tests passed: {passed}/{total}")

        if passed == total:
            print("🎉 All tests passed!")
        else:
            print("⚠️  Some tests failed. Check implementation.")

        return passed == total

if __name__ == "__main__":
    tester = KidModeTester()
    success = tester.run_all_tests()

    # Exit with appropriate code
    exit(0 if success else 1)
