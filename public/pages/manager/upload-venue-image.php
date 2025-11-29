<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Image Upload - Gatherly EMS</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Upload Venue Images</h1>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Select Venue</label>
            <select id="venueSelect" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="">Loading venues...</option>
            </select>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Select Image</label>
            <input type="file" id="imageFile" accept="image/*"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" id="isPrimary" class="mr-2">
                <span class="text-sm text-gray-700">Set as primary image</span>
            </label>
        </div>

        <div id="preview" class="mb-6 hidden">
            <p class="text-sm font-medium text-gray-700 mb-2">Preview:</p>
            <img id="previewImage" class="max-w-md rounded-lg shadow" alt="Preview">
        </div>

        <button id="uploadBtn"
            class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition-colors font-semibold">
            Upload Image
        </button>

        <div id="message" class="mt-4 hidden"></div>
    </div>

    <script>
        // Load venues
        async function loadVenues() {
            try {
                const response = await fetch('../../../src/services/manager/get-venues.php');
                const data = await response.json();

                const select = document.getElementById('venueSelect');
                select.innerHTML = '<option value="">Select a venue...</option>';

                if (data.success && data.venues) {
                    data.venues.forEach(venue => {
                        const option = document.createElement('option');
                        option.value = venue.venue_id;
                        option.textContent = venue.venue_name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading venues:', error);
            }
        }

        // Preview image
        document.getElementById('imageFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('preview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        // Upload image
        document.getElementById('uploadBtn').addEventListener('click', async function() {
            const venueId = document.getElementById('venueSelect').value;
            const imageFile = document.getElementById('imageFile').files[0];
            const isPrimary = document.getElementById('isPrimary').checked;

            if (!venueId || !imageFile) {
                showMessage('Please select both a venue and an image', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('venue_id', venueId);
            formData.append('image', imageFile);
            formData.append('is_primary', isPrimary ? '1' : '0');

            try {
                const response = await fetch('../../../src/services/manager/upload-venue-image.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Image uploaded successfully!', 'success');
                    document.getElementById('imageFile').value = '';
                    document.getElementById('preview').classList.add('hidden');
                } else {
                    showMessage('Error: ' + data.message, 'error');
                }
            } catch (error) {
                showMessage('Error uploading image: ' + error.message, 'error');
            }
        });

        function showMessage(text, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = text;
            messageDiv.className = `mt-4 p-4 rounded-lg ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
            messageDiv.classList.remove('hidden');

            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 5000);
        }

        // Initialize
        loadVenues();
    </script>
</body>

</html>