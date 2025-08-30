document.getElementById('visitorForm').addEventListener('submit', function(e) {
    e.preventDefault();  // stops the form from navigating

    // get form values
    const name = document.getElementById('visitorName').value.trim();
    const email = document.getElementById('visitorEmail').value.trim();
    const phone = document.getElementById('visitorPhone').value.trim();
    const purpose = document.getElementById('visitPurpose').value;
    const host = document.getElementById('hostName').value.trim();
    const notes = document.getElementById('additionalNotes').value.trim();

    // create QR string
    let qrData = `Name: ${name}\nEmail: ${email}\nPhone: ${phone}\nPurpose: ${purpose}\nHost: ${host}\nNotes: ${notes}\nID: ${Date.now()}`;

    // generate QR image
    const qrImg = generateQRCode(qrData, 300);

    // show QR image
    const qrDiv = document.getElementById('qrcode');
    qrDiv.innerHTML = '';   // clear previous
    qrDiv.appendChild(qrImg);
});
