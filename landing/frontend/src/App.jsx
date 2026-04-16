import { useState, useEffect } from 'react'
import './App.css'

function App() {
  const [lastVisit, setLastVisit] = useState(null)

  useEffect(() => {
    const backendUrl = import.meta.env.VITE_BACKEND_URL || 'http://localhost:8000';
    
    // Connect via SSE to get updates whenever ANYONE visits
    const eventSource = new EventSource(`${backendUrl}/api/visit`);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      if (data) {
        setLastVisit(data);
      }
    };

    eventSource.onerror = (error) => {
      console.error('SSE connection error', error);
      eventSource.close();
    };

    return () => {
      eventSource.close();
    };
  }, []);

  const formatTime = (timestamp) => {
    if (!timestamp) return '';
    return new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      minute: 'numeric',
      second: 'numeric',
      hour12: true
    }).format(new Date(timestamp));
  }

  return (
    <div className="app-container">
      <nav className="navbar">
        <div className="nav-brand">
          REST API <span>Protection</span>
        </div>
        <div className="nav-socials">
          <a href="https://github.com/kevorteg/wp-api-protection" className="social-link github" target="_blank" rel="noreferrer" aria-label="GitHub">
            <svg viewBox="0 0 98 96" xmlns="http://www.w3.org/2000/svg">
              <path fillRule="evenodd" clipRule="evenodd" d="M48.854 0C21.839 0 0 22 0 49.217c0 21.756 13.993 40.172 33.405 46.69 2.427.49 3.316-1.059 3.316-2.362 0-1.141-.08-5.052-.08-9.127-13.59 2.934-16.42-5.867-16.42-5.867-2.184-5.704-5.42-7.17-5.42-7.17-4.448-3.015.324-3.015.324-3.015 4.934.326 7.523 5.052 7.523 5.052 4.367 7.496 11.404 5.378 14.235 4.074.404-3.178 1.699-5.378 3.074-6.6-10.839-1.141-22.243-5.378-22.243-24.283 0-5.378 1.94-9.778 5.014-13.2-.485-1.222-2.184-6.275.486-13.038 0 0 4.125-1.304 13.426 5.052a46.97 46.97 0 0 1 12.214-1.63c4.125 0 8.33.571 12.213 1.63 9.302-6.356 13.427-5.052 13.427-5.052 2.67 6.763.97 11.816.485 13.038 3.155 3.422 5.015 7.822 5.015 13.2 0 18.905-11.404 23.06-22.324 24.283 1.78 1.548 3.316 4.481 3.316 9.126 0 6.6-.08 11.897-.08 13.526 0 1.304.89 2.853 3.316 2.364 19.412-6.52 33.405-24.935 33.405-46.691C97.707 22 75.868 0 48.854 0z" />
            </svg>
          </a>
          <a href="https://twitter.com/kevorteg" className="social-link" target="_blank" rel="noreferrer" aria-label="X (Twitter)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M4 4l11.733 16h4.267l-11.733 -16z" />
              <path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772" />
            </svg>
          </a>
        </div>
      </nav>

      <main className="hero-container">
        <div className="hero-content">
          <h1 className="hero-tagline">
            The Security Tool <br/>
            for <span className="highlight">WordPress</span>
          </h1>
          <p className="hero-desc">
            REST API Protection is a blazing fast security plugin that prevents automated scraping and data leaks by blocking default WordPress endpoints.
          </p>
          
          <div className="hero-actions">
            <a href="https://wordpress.org/plugins/wp-api-protection/" className="btn btn-primary" target="_blank" rel="noopener noreferrer">
              Get Started
            </a>
            <a href="https://github.com/kevorteg/wp-api-protection" className="btn btn-secondary" target="_blank" rel="noopener noreferrer">
              View on GitHub
            </a>
          </div>

          <div className="last-visit-badge">
            <div className="badge-row">
              <div className="ping"></div>
              Last visit from: 
              <span>
                {lastVisit ? `${lastVisit.city}, ${lastVisit.country}` : 'Scanning...'}
              </span>
            </div>
            {lastVisit?.time && (
              <div className="visit-time">
                at {formatTime(lastVisit.time)}
              </div>
            )}
          </div>
        </div>

        <div className="hero-graphic">
          <img src="/hero-graphic.png" alt="Abstract 3D Graphic" />
        </div>
      </main>
    </div>
  )
}

export default App
