import APIServices from '@/services/APIServices'

export function storeAccessToken(token: string) {
  localStorage.setItem('AccessToken', token)
}

export function getAccessToken() {
  return localStorage.getItem('AccessToken') ?? ''
}

export function deleteAccessToken() {
  localStorage.removeItem('AccessToken')
}

export async function deleteRefreshToken() {
  await APIServices.delete('/refresh')
}
