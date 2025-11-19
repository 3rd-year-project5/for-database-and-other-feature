package com.example.uidesign;

import android.os.Bundle;
import android.widget.TextView;
import android.widget.ImageButton;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;

import com.google.firebase.database.DataSnapshot;
import com.google.firebase.database.DatabaseError;
import com.google.firebase.database.DatabaseReference;
import com.google.firebase.database.FirebaseDatabase;
import com.google.firebase.database.ValueEventListener;

public class DashboardActivity extends AppCompatActivity {

    private TextView totalUsersCount, activeSessionsCount, logsCount;
    private ImageButton logOutB;

    // Firebase database reference
    private DatabaseReference dbRef;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_dash_board); // your XML

        // Initialize views
        totalUsersCount = findViewById(R.id.totalUsersCount);
        activeSessionsCount = findViewById(R.id.activeSessionsCount);
        logsCount = findViewById(R.id.logsCount);
        logOutB = findViewById(R.id.logOutB);

        // Logout button
        logOutB.setOnClickListener(v -> {
            // handle logout
            finish(); // just close activity for now
        });

        // Initialize Firebase reference
        dbRef = FirebaseDatabase.getInstance().getReference();

        // Load data from Firebase
        loadDashboardData();
    }

    private void loadDashboardData() {
        // Total Users
        dbRef.child("users").addListenerForSingleValueEvent(new ValueEventListener() {
            @Override
            public void onDataChange(@NonNull DataSnapshot snapshot) {
                long totalUsers = snapshot.getChildrenCount();
                totalUsersCount.setText(String.valueOf(totalUsers));
            }

            @Override
            public void onCancelled(@NonNull DatabaseError error) {
                totalUsersCount.setText("Error");
            }
        });

        // Active Sessions
        dbRef.child("sessions").addListenerForSingleValueEvent(new ValueEventListener() {
            @Override
            public void onDataChange(@NonNull DataSnapshot snapshot) {
                long activeSessions = 0;
                for (DataSnapshot s : snapshot.getChildren()) {
                    Boolean isActive = s.child("active").getValue(Boolean.class);
                    if (isActive != null && isActive) activeSessions++;
                }
                activeSessionsCount.setText(String.valueOf(activeSessions));
            }

            @Override
            public void onCancelled(@NonNull DatabaseError error) {
                activeSessionsCount.setText("Error");
            }
        });

        // Logs count
        dbRef.child("logs").addListenerForSingleValueEvent(new ValueEventListener() {
            @Override
            public void onDataChange(@NonNull DataSnapshot snapshot) {
                long logs = snapshot.getChildrenCount();
                logsCount.setText(String.valueOf(logs));
            }

            @Override
            public void onCancelled(@NonNull DatabaseError error) {
                logsCount.setText("Error");
            }
        });
    }
}
