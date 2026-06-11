# GPT Image Changer for WooCommerce

A WordPress plugin that uses ChatGPT's vision capabilities to optimize WooCommerce product images for better SEO.

## Description

GPT Image Changer automatically analyzes your WooCommerce product images using OpenAI's GPT-4 Vision model and generates SEO-optimized titles and alt text based on the visual content of the images and the product context.

### Key Features

- Automatically process WooCommerce product featured images and gallery images
- Generate SEO-optimized image titles and alt text
- Schedule automatic processing of images
- Track processing status with detailed logs
- Manual processing controls for individual images
- Configurable batch processing settings

## Requirements

- WordPress 5.6 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- OpenAI API key with access to GPT-4 Vision models

## Installation

1. Upload the `gpt-image-changer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to GPT Image Changer > Settings to configure your OpenAI API key

## Configuration

### API Settings

1. Go to GPT Image Changer > Settings
2. Enter your OpenAI API key
3. Select the appropriate GPT model (GPT-4 Vision Preview is recommended for best results)

### Processing Settings

- **Batch Size**: Number of images to process in each batch (default: 5)
- **Processing Schedule**: How often the automated processing should run (hourly, daily, twice daily, weekly)
- **Enable Processing**: Toggle to enable/disable automatic processing

## Usage

### Automatic Processing

Once configured, the plugin will automatically process WooCommerce product images according to the schedule you set.

### Manual Processing

1. Go to GPT Image Changer > Status
2. Click the "Process Images Now" button to manually process a batch of images
3. You can also process individual images by clicking the "Process Now" button next to an image in the status table

### Monitoring Status

The Status page provides a dashboard showing:

- Total processed images
- Pending images
- Failed images
- Last run time
- Next scheduled run
- Complete processing history

## How It Works

1. The plugin collects product featured images and gallery images from your WooCommerce products
2. It sends each image to the OpenAI GPT-4 Vision API along with product context (title, description, categories, tags)
3. The API analyzes the image and returns SEO-optimized title and alt text
4. The plugin updates the image metadata with the new information
5. The process is tracked in the status dashboard

## Troubleshooting

### Common Issues

- **API Key Invalid**: Verify your OpenAI API key is correct and has access to GPT-4 Vision models
- **Processing Never Completes**: Check your server's PHP timeout settings and consider reducing batch size
- **Images Not Being Processed**: Ensure WooCommerce products have associated images

### Logs

The plugin maintains detailed logs of all processing activities. These can be viewed in the Status page when WP_DEBUG is enabled.

## License

GPT Image Changer is licensed under the GPL v2 or later.

## Credits

This plugin was developed by [Your Name/Company] to help WooCommerce store owners optimize their product images for better SEO.

## Support

For support, please [create an issue](https://github.com/yourusername/gpt-image-changer/issues) on our GitHub repository or contact us through our website. 