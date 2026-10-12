package at.floatbox.aesculapp.dev;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        LegacyMedicationAlarmMigration.cancelOldRepeatingAlarms(this);
    }
}
