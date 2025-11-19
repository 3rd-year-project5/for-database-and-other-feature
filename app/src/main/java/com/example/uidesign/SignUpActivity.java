package com.example.uidesign;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;

import android.content.Intent;
import android.os.Bundle;
import android.util.Patterns;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import com.google.android.gms.tasks.OnCompleteListener;
import com.google.android.gms.tasks.Task;
import com.google.firebase.auth.AuthResult;
import com.google.firebase.auth.FirebaseAuth;

import java.util.ArrayList;
import java.util.List;

public class SignUpActivity extends AppCompatActivity {

    // Firebase
    private FirebaseAuth auth;

    // Fields
    private EditText inputFullName, inputEmail, inputPhone, inputHost;
    private Spinner spinnerPurpose;
    private Button signupButton;
    private TextView loginRedirectText;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_sign_up);

        auth = FirebaseAuth.getInstance();

        // ==== FIND UI COMPONENTS ====
        inputFullName = findViewById(R.id.inputFullName);
        inputEmail = findViewById(R.id.inputEmail);
        inputPhone = findViewById(R.id.inputPhone);
        inputHost = findViewById(R.id.inputHost);
        spinnerPurpose = findViewById(R.id.spinnerPurpose);
        signupButton = findViewById(R.id.btnSignUp);
        loginRedirectText = findViewById(R.id.loginRedirectText);

        // ==== SPINNER LIST ====
        List<String> purposeList = new ArrayList<>();
        purposeList.add("Select Purpose of Visit");
        purposeList.add("Meeting");
        purposeList.add("Delivery");
        purposeList.add("Appointment");
        purposeList.add("Walk-in");
        purposeList.add("Others");

        ArrayAdapter<String> adapter = new ArrayAdapter<>(
                this,
                android.R.layout.simple_spinner_item,
                purposeList
        );
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinnerPurpose.setAdapter(adapter);
        spinnerPurpose.setSelection(0);

        // ==== SIGN UP BUTTON CLICK ====
        signupButton.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {

                String fullName = inputFullName.getText().toString().trim();
                String email = inputEmail.getText().toString().trim();
                String phone = inputPhone.getText().toString().trim();
                String purpose = spinnerPurpose.getSelectedItem().toString();
                String host = inputHost.getText().toString().trim();

                // ==== VALIDATION ====
                if (fullName.isEmpty()) {
                    inputFullName.setError("Full name required");
                    return;
                }

                if (email.isEmpty()) {
                    inputEmail.setError("Email required");
                    return;
                }

                if (!Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
                    inputEmail.setError("Invalid email");
                    return;
                }

                if (phone.isEmpty()) {
                    inputPhone.setError("Phone number required");
                    return;
                }

                if (phone.length() < 10) {
                    inputPhone.setError("Invalid phone number");
                    return;
                }

                if (purpose.equals("Select Purpose of Visit")) {
                    Toast.makeText(SignUpActivity.this, "Please select purpose", Toast.LENGTH_SHORT).show();
                    return;
                }

                if (host.isEmpty()) {
                    inputHost.setError("Host person required");
                    return;
                }

                // ==== TEMP PASSWORD FOR FIREBASE ====
                String tempPassword = "visitor123";

                auth.createUserWithEmailAndPassword(email, tempPassword)
                        .addOnCompleteListener(new OnCompleteListener<AuthResult>() {
                            @Override
                            public void onComplete(@NonNull Task<AuthResult> task) {

                                if (task.isSuccessful()) {
                                    Toast.makeText(SignUpActivity.this,
                                            "Registration Successful",
                                            Toast.LENGTH_SHORT).show();

                                    startActivity(new Intent(SignUpActivity.this, LoginActivity.class));
                                    finish();

                                } else {
                                    Toast.makeText(SignUpActivity.this,
                                            "Signup Failed: " + task.getException().getMessage(),
                                            Toast.LENGTH_LONG).show();
                                }

                            }
                        });
            }
        });

        // ==== LOGIN REDIRECT ====
        loginRedirectText.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                startActivity(new Intent(SignUpActivity.this, LoginActivity.class));
                finish();
            }
        });
    }
}
