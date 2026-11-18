const {test, expect} = require('@playwright/test');

/**
 * End-to-end coverage for the primary site header.
 *
 * These tests open an isolated browser context, navigate to the configured
 * Playwright base URL, activate a header link or dropdown item, and verify
 * both the resulting URL and destination page heading. They cover top-level
 * menu items as well as links nested under "About the Archive" and
 * "Archive Online Store".
 */

//ToDo: cucumber to track test cases
//ToDo: how to handle when screen resizes and moves menu
  //Can use function page.setViewportSize({ width: 600, height: 600 })
  //By default, Astra’s breakpoints are:
  //Tablet breakpoint: 921px
  //Mobile breakpoint: 544px
  //Unable to find exact breakpoints in wordpress
//ToDo: create a class for link testing

//photo links:
//archive - top and bottom of page
//bluebell railway museum
//bluebell railway * 3




// Top-level menu links
test('Menu bar: Welcome', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //find and click on menu link for "Welcome"
  await page.getByRole('link', { name: 'Welcome' }).click();

  //check page is correct
  await expect(page).toHaveURL("/");
  await expect(page.locator('xpath=//*[@id="post-89"]/div/div[1]/h1')).toContainText('welcome to the website of the bluebell railway musEUm – Southern Railways Archive');
    
});

test('Menu bar: News and Articles', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //find and click on menu link for "News and Articles"
  await page.getByRole('link', { name: 'News & Articles' }).click();

  //check page is correct
  await expect(page).toHaveURL("/news-and-articles/");
  await expect(page.locator('h1')).toHaveText("news & Articles");
  
});

// "About the Archive" menu and dropdown links
test('Menu bar: About the Archive', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  await page.getByRole('link', { name: /About the Archive/ }).click();

  //check page is correct
  await expect(page).toHaveURL("/about-the-archive/");
  await expect(page.locator('h1')).toHaveText("About the archive");
  
});


test('Menu bar: About the Archive | Photograph Archive', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Photography Archive"
  await page.getByRole('link', { name: /About the Archive/ }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Photograph Archive' }).click();
    
  //check page is correct
  await expect(page).toHaveURL("/photographs-archive/");
  await expect(page.locator('h1')).toHaveText("photograph archive");
  
});

test('Menu bar: About the Archive | Reference Library', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Reference Library"
  await page.getByRole('link', { name: /About the Archive/ }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Reference Library' }).click();

  //check page is correct
  await expect(page).toHaveURL("/reference-library/");
  await expect(page.locator('h1')).toHaveText("reference library");
  
});

test('Menu bar: About the Archive | Document Archive', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Document Archive"
  await page.getByRole('link', { name: /About the Archive/ }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Document Archive' }).click();

  //check page is correct
  await expect(page).toHaveURL("/documents/");
  await expect(page.locator('h1')).toHaveText("DOCUMENT ARCHIVE");
  
});

test('Menu bar: About the Archive | Plans & Maps Archive', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Plans & Maps Archive"
  await page.getByRole('link', { name: /About the Archive/ }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Plans & Maps Archive' }).click();

  //check page is correct
  await expect(page).toHaveURL("/plans-and-maps-archive/");
  await expect(page.locator('h1')).toHaveText("plans & maps archive");
  
});

test('Menu bar: About the Archive | Bluebell Railway Archive', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Bluebell Railway Archive"
  await page.getByRole('link', { name: /About the Archive/ }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Bluebell Railway Archive' }).click();

  //check page is correct
  await expect(page).toHaveURL("/newsletter-signup/");
  await expect(page.locator('h1')).toHaveText("BLUEBELL RAILWAY ARCHIVE");
  
}); 

test('Menu bar: About the Archive | How to donate items', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "How to donate items"
  await page.getByRole('link', { name: /About the Archive/ }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'How to Donate Items' }).click();

  //check page is correct
  await expect(page).toHaveURL("/how-to-donate-items/");
  await expect(page.locator('h1')).toHaveText("How to Donate Items");
  
}); 
  
test('Menu bar: About the Archive | Support us', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Support us"
  await page.getByRole('link', { name: /About the Archive/ }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Support Us' }).click();

  //check page is correct
  await expect(page).toHaveURL("/support-us/");
  await expect(page.locator('h1')).toHaveText("Support Us");
  
}); 

test('Menu bar: About the Archive | Contact us & Visits', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Contact us & Visits"
  await page.getByRole('link', { name: 'About the Archive Menu Toggle' }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Contact us & Visits' }).click();

  //check page is correct
  await expect(page).toHaveURL("/contact-us-and-visits/");
  await expect(page.locator('h1')).toHaveText("Contact Us & visits");
  
}); 

// "Archive Online Store" menu and category links
test('Menu bar: Archive Online Store', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //Click on "Archive Online Store"
  await page.getByRole('link', { name: 'Archive Online Store Menu' }).click();

  //check page is correct
  await expect(page).toHaveURL("/shop/");
  await expect(page.locator('h1')).toHaveText("Archive Online Store");
  
});

test('Menu bar: Archive Online Store | Document Store', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Document Store"
  await page.getByRole('link', { name: 'Archive Online Store Menu' }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Document Store' }).click();

  //check page is correct
  await expect(page).toHaveURL("/product-category/documents/");
  await expect(page.locator('h1')).toHaveText("Documents");
  
});

test('Menu bar: Archive Online Store | Photograph Store', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //hover over dropdown then click on "Photograph Store"
  await page.getByRole('link', { name: 'Archive Online Store Menu' }).hover();
  await page.getByLabel('Primary Site Navigation').getByRole('link', { name: 'Photograph Store' }).click();

  //check page is correct
  await expect(page).toHaveURL("/product-category/photographs/");
  await expect(page.locator('h1')).toHaveText("Photographs");
  
});

// Shopping cart entry point
test('Menu bar: Basket', async ({ browser }) => {

  const context = await browser.newContext()

  const page = await context.newPage();
  
  //navigate to URL
  await page.goto("");

  //find and click on menu link for "Welcome"
  await page.getByRole('link', { name: /View Shopping Cart/ }).click();

  //check page is correct
  await expect(page).toHaveURL("/basket/");
  await expect(page.locator('h1')).toHaveText("Basket");  
  
});


//my account TODO: requires a login
//facebook



//TO RUN
//npx playwright test header.spec.js --headed --project chromium