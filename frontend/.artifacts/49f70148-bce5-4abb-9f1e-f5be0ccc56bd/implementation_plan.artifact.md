# Fix Proguard configuration error in @capacitor-community/contacts

The build is failing because the `@capacitor-community/contacts` plugin uses the deprecated `proguard-android.txt` file, which is no longer supported in newer Android Gradle Plugin versions.

## Proposed Changes

### [Component Name]

#### [MODIFY] [build.gradle](file:///C:/Develop/AppFindCash/lendus-find-mobile/frontend/node_modules/@capacitor-community/contacts/android/build.gradle)

Update line 35 to use `proguard-android-optimize.txt` instead of `proguard-android.txt`.

## Verification Plan

### Automated Tests
- Run `gradle sync` to verify the build evaluation issue is resolved.
