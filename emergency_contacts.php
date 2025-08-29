<?php
require_once 'config/config.php';
requireLogin();

// Ensure only parents can access this page
if ($_SESSION['role'] !== 'parent') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Emergency contacts data (in a real system, this would come from database)
$emergency_contacts = [
    [
        'name' => 'School Principal',
        'title' => 'Calumala Elementary School',
        'phone' => '+63 912 345 6789',
        'email' => 'principal@calumala-elem.edu.ph',
        'available' => '7:00 AM - 5:00 PM (Mon-Fri)',
        'type' => 'school'
    ],
    [
        'name' => 'School Security',
        'title' => 'Security Office',
        'phone' => '+63 912 345 6790',
        'email' => 'security@calumala-elem.edu.ph',
        'available' => '24/7',
        'type' => 'school'
    ],
    [
        'name' => 'Local Police Station',
        'title' => 'Barangay Police',
        'phone' => '117',
        'email' => 'police@barangay.gov.ph',
        'available' => '24/7',
        'type' => 'emergency'
    ],
    [
        'name' => 'Emergency Hotline',
        'title' => 'National Emergency',
        'phone' => '911',
        'email' => '',
        'available' => '24/7',
        'type' => 'emergency'
    ],
    [
        'name' => 'Barangay Captain',
        'title' => 'Local Government',
        'phone' => '+63 912 345 6791',
        'email' => 'captain@barangay.gov.ph',
        'available' => '8:00 AM - 5:00 PM (Mon-Fri)',
        'type' => 'government'
    ],
    [
        'name' => 'Child Protection Services',
        'title' => 'DSWD',
        'phone' => '+63 912 345 6792',
        'email' => 'childprotection@dswd.gov.ph',
        'available' => '8:00 AM - 5:00 PM (Mon-Fri)',
        'type' => 'government'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Contacts - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="emergency-container">
            <div class="emergency-header">
                <h1>🚨 Emergency Contacts</h1>
                <p>Important contacts for child safety and emergencies</p>
                <p><strong>In case of immediate danger, call 911 or 117</strong></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Emergency Instructions -->
            <div class="instructions-card">
                <h3>📋 What to do in an Emergency</h3>
                <ul class="instructions-list">
                    <li>Stay calm and assess the situation</li>
                    <li>If your child is missing, immediately contact school security and local police</li>
                    <li>Provide the child's full name, student ID, and last known location</li>
                    <li>Use the tracking system to share the child's last known GPS location</li>
                    <li>Contact other parents or guardians who might have seen your child</li>
                    <li>Stay available by phone for updates from authorities</li>
                </ul>
            </div>
            
            <!-- Emergency Contacts Grid -->
            <div class="emergency-grid">
                <?php foreach ($emergency_contacts as $contact): ?>
                <div class="contact-card <?php echo $contact['type']; ?>">
                    <div class="contact-header">
                        <div class="contact-icon <?php echo $contact['type']; ?>">
                            <?php 
                            switch($contact['type']) {
                                case 'school': echo '🏫'; break;
                                case 'government': echo '🏛️'; break;
                                case 'emergency': echo '🚨'; break;
                                default: echo '📞';
                            }
                            ?>
                        </div>
                        <div class="contact-info">
                            <h3><?php echo htmlspecialchars($contact['name']); ?></h3>
                            <div class="contact-title"><?php echo htmlspecialchars($contact['title']); ?></div>
                        </div>
                    </div>
                    
                    <div class="contact-details">
                        <div class="contact-item">
                            <span class="contact-item-icon">📞</span>
                            <strong><?php echo htmlspecialchars($contact['phone']); ?></strong>
                        </div>
                        
                        <?php if ($contact['email']): ?>
                        <div class="contact-item">
                            <span class="contact-item-icon">📧</span>
                            <?php echo htmlspecialchars($contact['email']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="contact-item">
                            <span class="contact-item-icon">🕒</span>
                            <?php echo htmlspecialchars($contact['available']); ?>
                        </div>
                    </div>
                    
                    <div class="contact-actions">
                        <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>" 
                           class="quick-dial <?php echo $contact['type'] === 'emergency' ? 'emergency' : ''; ?>">
                            📞 Call Now
                        </a>
                        
                        <?php if ($contact['email']): ?>
                        <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" class="btn btn-sm btn-secondary">
                            📧 Email
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($contact['type'] === 'emergency'): ?>
                        <button onclick="sendEmergencyAlert('<?php echo htmlspecialchars($contact['name']); ?>')" class="btn btn-sm btn-danger">
                            🚨 Emergency Alert
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Additional Resources -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📚 Additional Resources</h2>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h4>🏥 Medical Emergency</h4>
                        <ul>
                            <li><strong>Emergency Medical Services:</strong> 911</li>
                            <li><strong>Poison Control:</strong> +63 2 524 1078</li>
                            <li><strong>Red Cross:</strong> +63 2 527 0864</li>
                        </ul>
                        
                        <h4>👮 Law Enforcement</h4>
                        <ul>
                            <li><strong>National Emergency Hotline:</strong> 911</li>
                            <li><strong>PNP Hotline:</strong> 117</li>
                            <li><strong>Text Hotline:</strong> 2920 (Globe/TM)</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h4>🆘 Crisis Hotlines</h4>
                        <ul>
                            <li><strong>Child Protection Hotline:</strong> 1343</li>
                            <li><strong>DSWD Hotline:</strong> +63 2 931 8101</li>
                            <li><strong>Women and Children Protection:</strong> +63 2 410 3213</li>
                        </ul>
                        
                        <h4>🏫 School Resources</h4>
                        <ul>
                            <li><strong>Guidance Counselor:</strong> +63 912 345 6793</li>
                            <li><strong>School Nurse:</strong> +63 912 345 6794</li>
                            <li><strong>Parent-Teacher Association:</strong> +63 912 345 6795</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">⚡ Quick Actions</h2>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="parent_dashboard.php" class="btn btn-primary">🏠 Back to Dashboard</a>
                    <a href="my_children.php" class="btn btn-success">👨‍👩‍👧‍👦 View My Children</a>
                    <a href="alerts.php" class="btn btn-warning">🔔 Check Alerts</a>
                    <a href="my_cases.php" class="btn btn-danger">📁 View Cases</a>
                    <button onclick="shareEmergencyContacts()" class="btn btn-info">📤 Share Contacts</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        function sendEmergencyAlert(contactName) {
            if (confirm(`Are you sure you want to send an emergency alert to ${contactName}? This should only be used in genuine emergencies.`)) {
                // In a real implementation, this would send an emergency alert
                alert(`Emergency alert sent to ${contactName}. They will be notified immediately.`);
                
                // Log the emergency alert
                console.log(`Emergency alert sent to ${contactName} at ${new Date().toISOString()}`);
                
                // You could also redirect to a specific emergency page or open the phone dialer
                // window.location.href = 'tel:911';
            }
        }
        
        function shareEmergencyContacts() {
            const contactsText = `Emergency Contacts - Calumala Elementary School Child Tracking System:

🏫 School Principal: +63 912 345 6789
🔒 School Security: +63 912 345 6790 (24/7)
👮 Local Police: 117 (24/7)
🚨 Emergency Hotline: 911 (24/7)
🏛️ Barangay Captain: +63 912 345 6791
👶 Child Protection: +63 912 345 6792

In case of emergency, stay calm and contact the appropriate authority immediately.`;

            if (navigator.share) {
                navigator.share({
                    title: 'Emergency Contacts',
                    text: contactsText
                }).catch(console.error);
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(contactsText).then(() => {
                    alert('Emergency contacts copied to clipboard!');
                }).catch(() => {
                    // Fallback: show in alert
                    alert(contactsText);
                });
            }
        }
        
        // Add click tracking for emergency calls
        document.querySelectorAll('.quick-dial').forEach(button => {
            button.addEventListener('click', function(e) {
                const contactName = this.closest('.contact-card').querySelector('h3').textContent;
                console.log(`Emergency call initiated to: ${contactName}`);
                
                // In a real system, you might want to log this call for tracking purposes
                // or show a confirmation dialog for non-emergency contacts
            });
        });
        
        // Highlight emergency contacts
        document.addEventListener('DOMContentLoaded', function() {
            const emergencyCards = document.querySelectorAll('.contact-card.emergency');
            emergencyCards.forEach(card => {
                card.style.boxShadow = '0 2px 15px rgba(220, 53, 69, 0.2)';
            });
        });
        
        // Add keyboard shortcuts for quick access
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        window.location.href = 'tel:911';
                        break;
                    case '2':
                        e.preventDefault();
                        window.location.href = 'tel:117';
                        break;
                }
            }
        });
    </script>
</body>
</html>
