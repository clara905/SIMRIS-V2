import {
  signInWithPopup,
  signInWithEmailAndPassword,
  signOut,
} from 'firebase/auth'
import { firebaseAuth, googleProvider } from '../lib/firebase'
import { api } from '../api/client'

export async function loginWithGoogle() {
  await api.get('/sanctum/csrf-cookie')
  const result = await signInWithPopup(firebaseAuth, googleProvider)
  const idToken = await result.user.getIdToken()

  const response = await api.post('/api/auth/firebase', {
    idToken,
  })

  return response.data
}

export async function loginWithEmail(email: string, password: string) {
  await api.get('/sanctum/csrf-cookie')
  const result = await signInWithEmailAndPassword(firebaseAuth, email, password)
  const idToken = await result.user.getIdToken()

  const response = await api.post('/api/auth/firebase', {
    idToken,
  })

  return response.data
}

export async function logoutFirebase() {
  await signOut(firebaseAuth)
}