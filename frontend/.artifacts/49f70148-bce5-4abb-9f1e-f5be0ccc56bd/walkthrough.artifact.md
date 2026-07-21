# Walkthrough - Proguard Fix

I have fixed the build error related to `proguard-android.txt` in the `@capacitor-community/contacts` plugin.

## Changes

### `@capacitor-community/contacts`

#### [build.gradle](file:///C:/Develop/AppFindCash/lendus-find-mobile/frontend/node_modules/@capacitor-community/contacts/android/build.gradle)

Updated the Proguard configuration to use `proguard-android-optimize.txt`, as `proguard-android.txt` is no longer supported in modern versions of the Android Gradle Plugin.

```diff
-            proguardFiles getDefaultProguardFile('proguard-android.txt'), 'proguard-rules.pro'
+            proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'
```

## Verification Results

### Gradle Sync
Successfully ran `gradle sync`, which previously failed with the reported error.

> [!IMPORTANT]
> Since this change was made directly in `node_modules`, it will be lost if you run `npm install` (or `yarn install`) again. I recommend using a tool like [patch-package](https://www.npmjs.com/package/patch-package) to persist this fix until the plugin is updated by its maintainers.
