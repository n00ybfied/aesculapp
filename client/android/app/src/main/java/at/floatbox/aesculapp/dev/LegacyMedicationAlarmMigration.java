package at.floatbox.aesculapp.dev;

import android.app.AlarmManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;
import java.lang.reflect.Method;

/** Removes only the four fixed IDs used by the old daily medication reminders. */
final class LegacyMedicationAlarmMigration {
    private static final String TAG = "MedicationAlarmMigration";
    private static final int[] OLD_IDS = {830001, 830002, 830003, 830004};
    // EMUI stored the old broadcast with flg=0x14 and hwFlg=0x900.
    private static final int[] INTENT_FLAGS = {0, 0x14};
    private static final int HUAWEI_INTENT_FLAGS = 0x900;

    private LegacyMedicationAlarmMigration() {}

    static void cancelOldRepeatingAlarms(Context context) {
        AlarmManager alarms = (AlarmManager) context.getSystemService(Context.ALARM_SERVICE);
        if (alarms == null) return;

        int pendingFlags = PendingIntent.FLAG_NO_CREATE;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            pendingFlags |= PendingIntent.FLAG_MUTABLE;
        }

        for (int id : OLD_IDS) {
            for (int intentFlags : INTENT_FLAGS) {
                Intent intent = new Intent(context, com.capacitorjs.plugins.localnotifications.TimedNotificationPublisher.class);
                intent.setFlags(intentFlags);
                cancelIfPresent(context, alarms, id, intent, pendingFlags);
                if (setHuaweiFlags(intent)) {
                    cancelIfPresent(context, alarms, id, intent, pendingFlags);
                }
            }
        }
    }

    private static void cancelIfPresent(Context context, AlarmManager alarms, int id, Intent intent, int flags) {
        PendingIntent pending = PendingIntent.getBroadcast(context, id, intent, flags);
        if (pending == null) return;

        alarms.cancel(pending);
        pending.cancel();
        Log.i(TAG, "Cancelled old medication alarm " + id);
    }

    private static boolean setHuaweiFlags(Intent intent) {
        try {
            Method method = Intent.class.getMethod("setHwFlags", int.class);
            method.invoke(intent, HUAWEI_INTENT_FLAGS);
            return true;
        } catch (ReflectiveOperationException | SecurityException exception) {
            return false;
        }
    }
}
