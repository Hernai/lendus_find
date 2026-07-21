# Fix Proguard Build Error in `@capacitor-community/contacts`

The build is failing because the `@capacitor-community/contacts` plugin uses `proguard-android.txt`, which is no longer supported in recent versions of the Android Gradle Plugin (AGP). AGP now requires using `proguard-android-optimize.txt`.

## User Review Required

> [!IMPORTANT]
> This change modifies a file inside `node_modules`. While this fixes the immediate build error, please be aware that:
> 1. Running `npm install` or `yarn install` might overwrite this change.
> 2. It is recommended to use a tool like `patch-package` to persist this change if you cannot update the plugin to a version that has this fixed.
> 3. The plugin `@capacitor-community/contacts` seems to be using an older configuration compared to other plugins in the project.

## Proposed Changes

### Capacitor Plugins (`node_modules`)

#### [MODIFY] [build.gradle](file:///C:/Develop/AppFindCash/lendus-find-mobile/frontend/node_modules/@capacitor-community/contacts/android/build.gradle)

Update the `proguardFiles` configuration to use `proguard-android-optimize.txt`.

```diff
     buildTypes {
         release {
             minifyEnabled false
-            proguardFiles getDefaultProguardFile('proguard-android.txt'), 'proguard-rules.pro'
+            proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'
         }
     }
```

## Verification Plan

### Automated Tests
- I will attempt to run a gradle sync or build (if possible) to verify the error is gone.
- Since I cannot easily run a full build here without potentially long wait times, I'll check if `gradlew :capacitor-community-contacts:assembleRelease` (or similar) works.

### Manual Verification
- The user should try to build the project again after the fix.
