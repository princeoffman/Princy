# TODO: Remove Persistent Kid Mode and Replace with Non-Persistent Version

## Information Gathered
- Kid Mode feature includes persistent settings stored in localStorage ('userSettings', 'kidModeLocked', 'kidModeStartTime')
- Components: settings UI in index.html, monitoring functions in script.js, lock screen, test files
- Persistence allows Kid Mode to survive page reloads, which needs to be removed
- Need to replace with a simple, session-only Kid Mode that resets on page reload

## Plan
1. Remove Kid Mode settings section from index.html
2. Remove all Kid Mode related functions from script.js
3. Delete test files (test_kid_mode.js, test_kid_mode.html, test_kid_mode_node.js, test_kid_mode_manual.py)
4. Implement simple, non-persistent Kid Mode that only works in current session
5. Test the changes by opening the project in browser

## Dependent Files to be edited
- index.html: Remove Kid Mode settings section
- script.js: Remove persistent Kid Mode functions, add simple session-based version
- Delete test files

## Followup steps
- Verify Kid Mode no longer persists across reloads
- Test simple Kid Mode functionality in current session
- Open project in browser to confirm

## Progress
- [ ] Remove Kid Mode settings section from index.html
- [ ] Remove Kid Mode functions from script.js
- [ ] Delete test files
- [ ] Implement simple time warning
- [ ] Update time display click handler
- [ ] Test changes in browser
