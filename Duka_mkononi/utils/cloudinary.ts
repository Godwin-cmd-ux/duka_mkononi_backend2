import * as ImageManipulator from 'expo-image-manipulator';

// 🔥 CLOUDINARY CONFIG — shared across the app (adverts, profile photos, logos).
export const CLOUDINARY_CONFIG = {
  cloudName: 'dooidwbgt',
  uploadPreset: 'react_native_uploads',
};

// 🔥 MAX FILE SIZES (in bytes)
const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB
const MAX_VIDEO_SIZE = 20 * 1024 * 1024; // 20MB

const fetchWithTimeout = async (url: string, options: RequestInit, timeout = 60000) => {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeout);

  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
    });

    clearTimeout(timeoutId);
    return response;
  } catch (error) {
    clearTimeout(timeoutId);
    throw error;
  }
};

// 🔥 CHECK FILE SIZE
const checkFileSize = async (fileUri: string, type: 'image' | 'video'): Promise<boolean> => {
  const response = await fetch(fileUri);
  const blob = await response.blob();
  const size = blob.size;

  const maxSize = type === 'image' ? MAX_IMAGE_SIZE : MAX_VIDEO_SIZE;
  if (size > maxSize) {
    const maxSizeMB = maxSize / (1024 * 1024);
    throw new Error(`Faili ni kubwa sana! Kiwango cha juu: ${maxSizeMB}MB`);
  }
  return true;
};

// 🔥 COMPRESS IMAGE (before upload, keeps photos small)
const compressImage = async (uri: string): Promise<string> => {
  try {
    const manipResult = await ImageManipulator.manipulateAsync(
      uri,
      [{ resize: { width: 1080 } }],
      { compress: 0.7, format: ImageManipulator.SaveFormat.JPEG }
    );
    return manipResult.uri;
  } catch (error) {
    console.log('❌ Hitilafu ya ukandamizaji wa picha, tumia faili asili');
    return uri;
  }
};

// ☁️ Upload a local image/video to Cloudinary (unsigned upload with the shared preset).
export const uploadToCloudinary = async (
  fileUri: string,
  resourceType: 'image' | 'video' = 'image'
): Promise<{ secure_url: string; public_id: string }> => {
  try {
    console.log('☁️ Anzisha upakiaji wa Cloudinary...');

    let finalUri = fileUri;
    if (resourceType === 'image') {
      console.log('📸 Inakandamiza picha...');
      finalUri = await compressImage(fileUri);
    }

    await checkFileSize(finalUri, resourceType);

    const formData = new FormData();

    const fileName = finalUri.split('/').pop() || 'upload';
    const fileType = resourceType === 'video' ? 'video/mp4' : 'image/jpeg';

    console.log(`📁 Faili: ${fileName}, Aina: ${fileType}`);

    formData.append('file', {
      uri: finalUri,
      type: fileType,
      name: fileName,
    } as any);

    formData.append('upload_preset', CLOUDINARY_CONFIG.uploadPreset);
    formData.append('cloud_name', CLOUDINARY_CONFIG.cloudName);

    console.log('📤 Inatuma kwa Cloudinary...');

    const timeout = resourceType === 'video' ? 180000 : 60000;

    const uploadResponse = await fetchWithTimeout(
      `https://api.cloudinary.com/v1_1/${CLOUDINARY_CONFIG.cloudName}/${resourceType}/upload`,
      {
        method: 'POST',
        body: formData,
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      },
      timeout
    );

    if (!uploadResponse.ok) {
      const errorText = await uploadResponse.text();
      console.error('❌ Upakiaji wa Cloudinary umeshindikana:', uploadResponse.status, errorText);
      throw new Error(`Upakiaji umeshindikana: ${uploadResponse.status}`);
    }

    const data = await uploadResponse.json();
    console.log('✅ Cloudinary upload successful');

    return {
      secure_url: data.secure_url,
      public_id: data.public_id,
    };
  } catch (error: any) {
    console.error('❌ Hitilafu ya upakiaji wa Cloudinary:', error.message);

    if (error.name === 'AbortError') {
      throw new Error('Muda wa upakiaji umekwisha. Tafadhali angalia muunganiko wako wa intaneti na ujaribu tena.');
    }

    throw new Error(`Upakiaji wa Cloudinary umeshindikana: ${error.message}`);
  }
};
