const { test, expect } = require('@playwright/test');

test('WPForms event tracking fires and logs to console', async ({ page }) => {
  // Use the correct local WordPress site URL
  await page.goto('http://localhost/wordpress/');

  // Listen for console messages
  let eventTracked = false;
  page.on('console', msg => {
    if (msg.type() === 'log' && msg.text().includes('WPForms Submission Tracked')) {
      eventTracked = true;
    }
  });

  // Mock gtag to prevent errors in the event handler
  await page.evaluate(() => {
    window.gtag = function () { /* mock */ };
  });

  // Simulate the wpformsSubmit event
  await page.evaluate(() => {
    const event = new CustomEvent('wpformsSubmit', {
      detail: { formId: 123, formName: 'Test Form' }
    });
    document.dispatchEvent(event);
  });

  // Wait a moment for the event to process
  await page.waitForTimeout(500);

  // Assert the event was tracked
  expect(eventTracked).toBeTruthy();
});
